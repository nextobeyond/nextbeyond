"""
Diagram Renderer for Exam DOCX Export Engine.
Renders academic-quality vector diagrams to in-memory PNG buffers at 200 DPI using Matplotlib.
Supports:
1. coordinate_graph
2. function_graph (with safe AST-based expression parsing - no eval)
3. geometry (triangle, rectangle, square, circle, polygon, angles, sides)
4. bar_chart
5. number_line
"""

import io
import math
import logging
import ast
import numpy as np
import matplotlib
matplotlib.use("Agg")  # Non-interactive backend
import matplotlib.pyplot as plt
import matplotlib.patches as patches
from matplotlib.patches import Arc, FancyArrowPatch
from font_manager import get_thai_font

logger = logging.getLogger("diagram_renderer")
if not logger.handlers:
    logging.basicConfig(level=logging.INFO)

# Base visual style parameters
LABEL_BBOX = dict(
    facecolor="white",
    alpha=0.85,
    edgecolor="none",
    pad=1.5
)


# ==============================================================================
# Safe Expression Parser for Function Graphs (NEVER uses unsafe eval())
# ==============================================================================

ALLOWED_AST_NODES = (
    ast.Expression,
    ast.BinOp,
    ast.UnaryOp,
    ast.Constant,
    ast.Num,  # for python < 3.8 compatibility if needed
    ast.Name,
    ast.Call,
    ast.Add,
    ast.Sub,
    ast.Mult,
    ast.Div,
    ast.Pow,
    ast.USub,
    ast.UAdd,
    ast.Mod,
    ast.Load,
)

SAFE_FUNCTIONS = {
    "abs": np.abs,
    "sin": np.sin,
    "cos": np.cos,
    "tan": np.tan,
    "sqrt": np.sqrt,
    "exp": np.exp,
    "log": np.log,
    "ln": np.log,
}

SAFE_CONSTANTS = {
    "pi": np.pi,
    "e": np.e,
}


def sanitize_math_expression(expr: str) -> str:
    """Standardizes math notation into Python syntax (e.g. 2x -> 2*x, x^2 -> x**2, |x| -> abs(x))."""
    s = expr.strip()
    # Replace ^ with **
    s = s.replace("^", "**")
    
    # Handle |expression| -> abs(expression)
    # Simple pairs of pipes
    parts = s.split("|")
    if len(parts) >= 3 and len(parts) % 2 == 1:
        new_s = []
        for i, part in enumerate(parts):
            if i % 2 == 1:
                new_s.append(f"abs({part})")
            else:
                new_s.append(part)
        s = "".join(new_s)

    # Insert * between digit and variable or parenthesis, e.g. 2x -> 2*x, 3(x) -> 3*(x)
    res = []
    for i, ch in enumerate(s):
        res.append(ch)
        if i + 1 < len(s):
            next_ch = s[i + 1]
            if (ch.isdigit() and (next_ch.isalpha() or next_ch == '(')) or \
               (ch == 'x' and (next_ch == '(' or next_ch.isdigit())):
                res.append('*')
            elif ch == ')' and (next_ch.isalpha() or next_ch.isdigit() or next_ch == '('):
                res.append('*')
    return "".join(res)


def safe_eval_math(expr_str: str, x_values: np.ndarray) -> np.ndarray:
    """Evaluates a mathematical expression safely using AST inspection."""
    cleaned = sanitize_math_expression(expr_str)
    
    try:
        tree = ast.parse(cleaned, mode="eval")
    except Exception as e:
        raise ValueError(f"Invalid math expression syntax '{expr_str}': {e}")

    # Verify every node is strictly whitelisted
    for node in ast.walk(tree):
        if not isinstance(node, ALLOWED_AST_NODES):
            raise ValueError(f"Security: Expression contains unauthorized construct '{type(node).__name__}'")
        if isinstance(node, ast.Name):
            if node.id not in ("x", "pi", "e") and node.id not in SAFE_FUNCTIONS:
                raise ValueError(f"Security: Variable '{node.id}' is not allowed")
        elif isinstance(node, ast.Call):
            if not isinstance(node.func, ast.Name) or node.func.id not in SAFE_FUNCTIONS:
                func_name = getattr(node.func, 'id', str(node.func))
                raise ValueError(f"Security: Function '{func_name}' is not allowed")

    # Evaluation helper
    def _eval_node(node):
        if isinstance(node, ast.Expression):
            return _eval_node(node.body)
        elif isinstance(node, (ast.Constant, ast.Num)):
            return node.value if hasattr(node, 'value') else node.n
        elif isinstance(node, ast.Name):
            if node.id == "x":
                return x_values
            elif node.id in SAFE_CONSTANTS:
                return SAFE_CONSTANTS[node.id]
            raise ValueError(f"Unknown name: {node.id}")
        elif isinstance(node, ast.UnaryOp):
            val = _eval_node(node.operand)
            if isinstance(node.op, ast.USub):
                return -val
            elif isinstance(node.op, ast.UAdd):
                return val
        elif isinstance(node, ast.BinOp):
            left = _eval_node(node.left)
            right = _eval_node(node.right)
            if isinstance(node.op, ast.Add):
                return left + right
            elif isinstance(node.op, ast.Sub):
                return left - right
            elif isinstance(node.op, ast.Mult):
                return left * right
            elif isinstance(node.op, ast.Div):
                with np.errstate(divide='ignore', invalid='ignore'):
                    return left / right
            elif isinstance(node.op, ast.Pow):
                with np.errstate(invalid='ignore'):
                    return np.power(left, right)
            elif isinstance(node.op, ast.Mod):
                return left % right
        elif isinstance(node, ast.Call):
            func_name = node.func.id
            fn = SAFE_FUNCTIONS[func_name]
            args = [_eval_node(arg) for arg in node.args]
            return fn(*args)
        raise ValueError(f"Unsupported node type: {type(node).__name__}")

    return _eval_node(tree)


# ==============================================================================
# Export Helper: Converts Figure to In-Memory PNG Buffer
# ==============================================================================

def fig_to_buffer(fig) -> io.BytesIO:
    """Exports a Matplotlib figure directly to an in-memory BytesIO PNG at 200 DPI."""
    buffer = io.BytesIO()
    try:
        fig.tight_layout()
    except Exception:
        pass
    fig.savefig(buffer, format="png", dpi=200, bbox_inches="tight")
    buffer.seek(0)
    plt.close(fig)
    return buffer


# ==============================================================================
# Diagram 1: Coordinate Graph
# ==============================================================================

def create_coordinate_graph(params: dict) -> io.BytesIO:
    """
    Renders a 2D Cartesian coordinate plane with axes arrows, grid, and optional points/lines.
    params:
      x_min, x_max, y_min, y_max (default: -5 to 5)
      points: list of {"x": 3, "y": 4, "label": "A", "color": "#2563eb"}
      lines: list of [{"from": {"x": 0, "y": 0}, "to": {"x": 3, "y": 4}, "color": "..."}]
      title: optional title
    """
    thai_font = get_thai_font(12)
    thai_font_bold = get_thai_font(13, weight="bold")
    thai_font_small = get_thai_font(10)

    x_min = float(params.get("x_min", -5))
    x_max = float(params.get("x_max", 5))
    y_min = float(params.get("y_min", -5))
    y_max = float(params.get("y_max", 5))

    fig, ax = plt.subplots(figsize=(4.8, 4.8), dpi=200)
    fig.patch.set_facecolor("white")
    ax.set_facecolor("white")

    # Grid
    ax.set_xlim(x_min - 0.6, x_max + 0.6)
    ax.set_ylim(y_min - 0.6, y_max + 0.6)
    
    x_ticks = [i for i in range(int(math.floor(x_min)), int(math.ceil(x_max)) + 1) if i != 0]
    y_ticks = [i for i in range(int(math.floor(y_min)), int(math.ceil(y_max)) + 1) if i != 0]
    ax.set_xticks(x_ticks)
    ax.set_yticks(y_ticks)
    ax.grid(True, linestyle="--", linewidth=0.6, color="#cbd5e1", alpha=0.7, zorder=1)

    # Clean axes through center
    ax.axhline(0, color="#1e293b", linewidth=1.2, zorder=2)
    ax.axvline(0, color="#1e293b", linewidth=1.2, zorder=2)

    # Arrows for axes
    arrow_props = dict(arrowstyle="-|>", color="#1e293b", lw=1.2, mutation_scale=12)
    ax.annotate("", xy=(x_max + 0.5, 0), xytext=(x_max, 0), arrowprops=arrow_props, zorder=3)
    ax.annotate("", xy=(0, y_max + 0.5), xytext=(0, y_max), arrowprops=arrow_props, zorder=3)

    # Axis labels
    ax.text(x_max + 0.55, 0, "x", fontproperties=thai_font_bold, va="center", ha="left", color="#1e293b", zorder=4)
    ax.text(0, y_max + 0.55, "y", fontproperties=thai_font_bold, va="bottom", ha="center", color="#1e293b", zorder=4)
    ax.text(-0.25, -0.35, "0", fontproperties=thai_font_small, va="top", ha="right", color="#64748b", zorder=4)

    # Tick labels font
    for label in ax.get_xticklabels():
        label.set_fontproperties(thai_font_small)
        label.set_color("#64748b")
    for label in ax.get_yticklabels():
        label.set_fontproperties(thai_font_small)
        label.set_color("#64748b")

    # Hide box spines
    for spine in ax.spines.values():
        spine.set_visible(False)

    # Optional lines
    lines = params.get("lines", [])
    for line in lines:
        p1 = line.get("from", {})
        p2 = line.get("to", {})
        ax.plot([p1.get("x", 0), p2.get("x", 0)], [p1.get("y", 0), p2.get("y", 0)],
                color=line.get("color", "#2563eb"), linewidth=1.5, zorder=3)

    # Points
    points = params.get("points", [])
    for pt in points:
        px = float(pt.get("x", 0))
        py = float(pt.get("y", 0))
        label = pt.get("label", "")
        color = pt.get("color", "#0284c7")

        # Dashed projection lines to axes
        ax.plot([px, px], [0, py], color="#94a3b8", linestyle=":", linewidth=1, zorder=2)
        ax.plot([0, px], [py, py], color="#94a3b8", linestyle=":", linewidth=1, zorder=2)

        # Marker
        ax.scatter([px], [py], color=color, s=55, zorder=5, edgecolors="#0f172a", linewidth=0.8)

        # Label
        if label:
            display_text = f"{label} ({int(px) if px.is_integer() else px}, {int(py) if py.is_integer() else py})" if pt.get("show_coords", True) else label
            ax.text(px + 0.25, py + 0.25, display_text, fontproperties=thai_font_bold,
                    bbox=LABEL_BBOX, zorder=6, color="#0f172a")

    title = params.get("title")
    if title:
        ax.set_title(title, fontproperties=thai_font_bold, pad=12, color="#0f172a")

    return fig_to_buffer(fig)


# ==============================================================================
# Diagram 2: Function Graph
# ==============================================================================

def create_function_graph(params: dict) -> io.BytesIO:
    """
    Renders one or more mathematical function curves safely.
    params:
      equation: string (e.g. "x**2", "2x + 1", "|x|", "sin(x)") or list of equations
      x_min, x_max, y_min, y_max (default: -5 to 5)
      important_points: list of {"x": ..., "y": ..., "label": "..."}
      title: optional title
    """
    thai_font = get_thai_font(12)
    thai_font_bold = get_thai_font(13, weight="bold")
    thai_font_small = get_thai_font(10)

    x_min = float(params.get("x_min", -5))
    x_max = float(params.get("x_max", 5))
    y_min = float(params.get("y_min", -5))
    y_max = float(params.get("y_max", 5))

    fig, ax = plt.subplots(figsize=(5.0, 4.5), dpi=200)
    fig.patch.set_facecolor("white")
    ax.set_facecolor("white")

    ax.set_xlim(x_min - 0.5, x_max + 0.5)
    ax.set_ylim(y_min - 0.5, y_max + 0.5)

    # Grid & Axes
    ax.grid(True, linestyle="--", linewidth=0.6, color="#cbd5e1", alpha=0.7, zorder=1)
    ax.axhline(0, color="#1e293b", linewidth=1.2, zorder=2)
    ax.axvline(0, color="#1e293b", linewidth=1.2, zorder=2)

    arrow_props = dict(arrowstyle="-|>", color="#1e293b", lw=1.2, mutation_scale=12)
    ax.annotate("", xy=(x_max + 0.45, 0), xytext=(x_max, 0), arrowprops=arrow_props, zorder=3)
    ax.annotate("", xy=(0, y_max + 0.45), xytext=(0, y_max), arrowprops=arrow_props, zorder=3)

    ax.text(x_max + 0.5, 0, "x", fontproperties=thai_font_bold, va="center", ha="left", color="#1e293b", zorder=4)
    ax.text(0, y_max + 0.5, "y", fontproperties=thai_font_bold, va="bottom", ha="center", color="#1e293b", zorder=4)
    ax.text(-0.25, -0.3, "0", fontproperties=thai_font_small, va="top", ha="right", color="#64748b", zorder=4)

    for label in ax.get_xticklabels():
        label.set_fontproperties(thai_font_small)
        label.set_color("#64748b")
    for label in ax.get_yticklabels():
        label.set_fontproperties(thai_font_small)
        label.set_color("#64748b")

    for spine in ax.spines.values():
        spine.set_visible(False)

    # Plot curves
    equations = params.get("equations") or params.get("equation")
    if isinstance(equations, str):
        equations = [{"expr": equations, "label": f"y = {equations}"}]
    elif isinstance(equations, dict):
        equations = [equations]
    elif not isinstance(equations, list):
        equations = []

    palette = ["#2563eb", "#dc2626", "#16a34a", "#7c3aed", "#d97706"]
    x_vals = np.linspace(x_min, x_max, 500)

    has_legend = False
    for idx, eq in enumerate(equations):
        expr = eq.get("expr", "") if isinstance(eq, dict) else str(eq)
        label_text = eq.get("label", expr) if isinstance(eq, dict) else expr
        color = eq.get("color", palette[idx % len(palette)]) if isinstance(eq, dict) else palette[idx % len(palette)]
        
        try:
            y_vals = safe_eval_math(expr, x_vals)
            # Mask values out of display range to avoid distortion
            y_vals_masked = np.copy(y_vals)
            y_vals_masked[y_vals_masked < (y_min - 2)] = np.nan
            y_vals_masked[y_vals_masked > (y_max + 2)] = np.nan
            
            line, = ax.plot(x_vals, y_vals_masked, color=color, linewidth=2.0, zorder=3, label=label_text)
            if label_text and len(equations) > 1:
                has_legend = True
        except Exception as e:
            logger.warning(f"Error plotting curve '{expr}': {e}")

    if has_legend:
        leg = ax.legend(prop=thai_font, framealpha=0.9, facecolor="white", edgecolor="#cbd5e1")
        leg.set_zorder(10)

    # Highlighted points
    points = params.get("points", []) or params.get("important_points", [])
    for pt in points:
        px = float(pt.get("x", 0))
        py = float(pt.get("y", 0))
        label = pt.get("label", "")
        color = pt.get("color", "#dc2626")
        ax.scatter([px], [py], color=color, s=55, zorder=5, edgecolors="#0f172a", linewidth=0.8)
        if label:
            ax.text(px + 0.2, py + 0.2, label, fontproperties=thai_font_bold, bbox=LABEL_BBOX, zorder=6)

    title = params.get("title")
    if title:
        ax.set_title(title, fontproperties=thai_font_bold, pad=12, color="#0f172a")

    return fig_to_buffer(fig)


# ==============================================================================
# Diagram 3: Geometry Diagram
# ==============================================================================

def create_geometry_diagram(params: dict) -> io.BytesIO:
    """
    Renders geometric figures (triangle, rectangle, square, circle, polygon, angles, sides).
    params:
      shape: "triangle", "rectangle", "square", "circle", "polygon"
      points: list of {"x": ..., "y": ..., "label": "A"}
      sides: list of {"from": "A", "to": "B", "label": "5 cm"}
      angles: list of {"vertex": "B", "label": "90°", "right_angle": true}
      title: optional title
    """
    thai_font = get_thai_font(12)
    thai_font_bold = get_thai_font(13, weight="bold")
    thai_font_small = get_thai_font(10)

    shape_type = params.get("shape", "triangle").lower()
    points = params.get("points", [])

    # Default coordinate presets if none provided
    if not points:
        if shape_type == "triangle":
            points = [
                {"x": 0.0, "y": 0.0, "label": "A"},
                {"x": 4.0, "y": 0.0, "label": "B"},
                {"x": 2.0, "y": 3.0, "label": "C"}
            ]
        elif shape_type in ("rectangle", "square"):
            h = 2.5 if shape_type == "rectangle" else 3.0
            points = [
                {"x": 0.0, "y": 0.0, "label": "A"},
                {"x": 4.0, "y": 0.0, "label": "B"},
                {"x": 4.0, "y": h, "label": "C"},
                {"x": 0.0, "y": h, "label": "D"}
            ]
        elif shape_type == "circle":
            radius = float(params.get("radius", 2.0))
            center = params.get("center", {"x": 0.0, "y": 0.0, "label": "O"})
            points = [center]

    fig, ax = plt.subplots(figsize=(4.8, 4.2), dpi=200)
    fig.patch.set_facecolor("white")
    ax.set_facecolor("white")
    ax.set_aspect("equal", adjustable="datalim")
    ax.axis("off")  # Clean academic illustration without frame

    # Draw shapes
    if shape_type == "circle":
        radius = float(params.get("radius", 2.0))
        center_x = float(points[0].get("x", 0.0))
        center_y = float(points[0].get("y", 0.0))
        circle = plt.Circle((center_x, center_y), radius, fill=True,
                            facecolor="#f1f5f9", edgecolor="#1e293b", linewidth=1.6, zorder=2)
        ax.add_patch(circle)
        
        # Center marker
        ax.scatter([center_x], [center_y], color="#1e293b", s=30, zorder=4)
        if points[0].get("label"):
            ax.text(center_x + 0.15, center_y + 0.15, points[0].get("label"),
                    fontproperties=thai_font_bold, bbox=LABEL_BBOX, zorder=5)
            
        # Optional radius line
        if params.get("show_radius", True):
            ax.plot([center_x, center_x + radius], [center_y, center_y],
                    color="#2563eb", linestyle="-", linewidth=1.2, zorder=3)
            ax.text(center_x + radius / 2, center_y + 0.15, f"r = {radius}",
                    fontproperties=thai_font_small, ha="center", bbox=LABEL_BBOX, zorder=5)
            
        ax.set_xlim(center_x - radius - 0.8, center_x + radius + 0.8)
        ax.set_ylim(center_y - radius - 0.8, center_y + radius + 0.8)
    else:
        # Polygon / Triangle / Rectangle
        poly_coords = [(float(pt["x"]), float(pt["y"])) for pt in points]
        polygon = patches.Polygon(poly_coords, closed=True,
                                 facecolor="#f8fafc", edgecolor="#1e293b",
                                 linewidth=1.6, zorder=2)
        ax.add_patch(polygon)

        # Plot vertices & labels
        pt_map = {pt.get("label", str(i)): pt for i, pt in enumerate(points)}
        
        # Calculate centroid for clean outward label placement
        cx = sum(p[0] for p in poly_coords) / len(poly_coords)
        cy = sum(p[1] for p in poly_coords) / len(poly_coords)

        for pt in points:
            px = float(pt.get("x", 0))
            py = float(pt.get("y", 0))
            label = pt.get("label", "")
            ax.scatter([px], [py], color="#1e293b", s=35, zorder=4)
            if label:
                # Vector from centroid outwards
                dx = px - cx
                dy = py - cy
                dist = math.hypot(dx, dy)
                if dist > 0:
                    offset_x = (dx / dist) * 0.35
                    offset_y = (dy / dist) * 0.35
                else:
                    offset_x, offset_y = 0.2, 0.2
                ax.text(px + offset_x, py + offset_y, label,
                        fontproperties=thai_font_bold, ha="center", va="center",
                        bbox=LABEL_BBOX, zorder=6)

        # Side labels
        sides = params.get("sides", [])
        for side in sides:
            p_from = pt_map.get(side.get("from"))
            p_to = pt_map.get(side.get("to"))
            s_label = side.get("label", "")
            if p_from and p_to and s_label:
                mx = (float(p_from["x"]) + float(p_to["x"])) / 2
                my = (float(p_from["y"]) + float(p_to["y"])) / 2
                ax.text(mx, my, s_label, fontproperties=thai_font_small,
                        ha="center", va="center", bbox=LABEL_BBOX, zorder=5)

        # Angles & Right-angle markers
        angles = params.get("angles", [])
        for ang in angles:
            v_label = ang.get("vertex")
            target_pt = pt_map.get(v_label)
            if target_pt:
                vx = float(target_pt["x"])
                vy = float(target_pt["y"])
                if ang.get("right_angle"):
                    # Draw a right angle symbol (small square)
                    sq_size = 0.35
                    ax.add_patch(patches.Rectangle((vx, vy), sq_size, sq_size,
                                                   fill=False, edgecolor="#dc2626", linewidth=1.2, zorder=3))
                elif ang.get("label"):
                    ax.text(vx + 0.3, vy + 0.2, ang.get("label"),
                            fontproperties=thai_font_small, color="#dc2626", bbox=LABEL_BBOX, zorder=5)

    ax.margins(0.18)
    title = params.get("title")
    if title:
        ax.set_title(title, fontproperties=thai_font_bold, pad=12, color="#0f172a")

    return fig_to_buffer(fig)


# ==============================================================================
# Diagram 4: Bar Chart
# ==============================================================================

def create_bar_chart(params: dict) -> io.BytesIO:
    """
    Renders an academic categorical bar chart.
    params:
      categories: ["ม.1", "ม.2", "ม.3", "ม.4"]
      values: [24, 30, 18, 35]
      title: "จำนวนนักเรียนในแต่ละระดับชั้น"
      x_label, y_label
    """
    thai_font = get_thai_font(12)
    thai_font_bold = get_thai_font(13, weight="bold")
    thai_font_small = get_thai_font(11)

    categories = [str(c) for c in params.get("categories", ["A", "B", "C", "D"])]
    values = [float(v) for v in params.get("values", [10, 20, 15, 25])]

    fig, ax = plt.subplots(figsize=(5.2, 3.8), dpi=200)
    fig.patch.set_facecolor("white")
    ax.set_facecolor("white")

    # Bar styling
    colors = params.get("colors") or ["#3b82f6", "#60a5fa", "#93c5fd", "#2563eb", "#1d4ed8"]
    bar_colors = [colors[i % len(colors)] for i in range(len(categories))]

    x_indices = np.arange(len(categories))
    bars = ax.bar(x_indices, values, width=0.55, color=bar_colors, edgecolor="#1e293b", linewidth=1.0, zorder=3)

    # Grid on Y axis only
    ax.grid(True, axis="y", linestyle="--", linewidth=0.6, color="#cbd5e1", alpha=0.7, zorder=1)
    ax.set_axisbelow(True)

    # Clean axes spines
    ax.spines["top"].set_visible(False)
    ax.spines["right"].set_visible(False)
    ax.spines["left"].set_color("#64748b")
    ax.spines["bottom"].set_color("#64748b")

    # X and Y Ticks
    ax.set_xticks(x_indices)
    ax.set_xticklabels(categories, fontproperties=thai_font)
    for label in ax.get_yticklabels():
        label.set_fontproperties(thai_font_small)
        label.set_color("#475569")

    # Labels on top of bars
    max_val = max(values) if values else 1
    ax.set_ylim(0, max_val * 1.18)
    for bar in bars:
        h = bar.get_height()
        val_str = f"{int(h)}" if h.is_integer() else f"{h:.1f}"
        ax.text(bar.get_x() + bar.get_width() / 2, h + (max_val * 0.02),
                val_str, fontproperties=thai_font_bold, ha="center", va="bottom",
                color="#0f172a", zorder=4)

    # Axis titles & chart title
    x_label = params.get("x_label")
    if x_label:
        ax.set_xlabel(x_label, fontproperties=thai_font, labelpad=8, color="#334155")
        
    y_label = params.get("y_label")
    if y_label:
        ax.set_ylabel(y_label, fontproperties=thai_font, labelpad=8, color="#334155")

    title = params.get("title")
    if title:
        ax.set_title(title, fontproperties=thai_font_bold, pad=12, color="#0f172a")

    return fig_to_buffer(fig)


# ==============================================================================
# Diagram 5: Number Line
# ==============================================================================

def create_number_line(params: dict) -> io.BytesIO:
    """
    Renders a 1D real number line with ticks, open/closed points, and intervals/rays.
    params:
      min: integer minimum (default: -5)
      max: integer maximum (default: 5)
      points: list of {"x": 2, "label": "x=2", "closed": true}
      intervals: list of:
        {"from": -2, "to": 3, "include_from": false, "include_to": true, "color": "#2563eb"}
        {"from": 1, "direction": "right", "include_from": true}
    """
    thai_font = get_thai_font(12)
    thai_font_bold = get_thai_font(13, weight="bold")
    thai_font_small = get_thai_font(11)

    n_min = int(params.get("min", -5))
    n_max = int(params.get("max", 5))
    if n_min >= n_max:
        n_min, n_max = -5, 5

    fig, ax = plt.subplots(figsize=(5.6, 2.0), dpi=200)
    fig.patch.set_facecolor("white")
    ax.set_facecolor("white")
    ax.axis("off")

    y_line = 0.0
    line_margin = 0.8
    ax.set_xlim(n_min - line_margin, n_max + line_margin)
    ax.set_ylim(-1.0, 1.3)

    # Main horizontal number line
    ax.plot([n_min - 0.5, n_max + 0.5], [y_line, y_line], color="#1e293b", linewidth=1.5, zorder=2)

    # Arrowheads at both ends
    arrow_props = dict(arrowstyle="-|>", color="#1e293b", lw=1.5, mutation_scale=12)
    ax.annotate("", xy=(n_max + 0.65, y_line), xytext=(n_max + 0.3, y_line), arrowprops=arrow_props, zorder=2)
    ax.annotate("", xy=(n_min - 0.65, y_line), xytext=(n_min - 0.3, y_line), arrowprops=arrow_props, zorder=2)

    # Integer tick marks and numeric labels below
    for val in range(n_min, n_max + 1):
        ax.plot([val, val], [y_line - 0.12, y_line + 0.12], color="#1e293b", linewidth=1.2, zorder=3)
        ax.text(val, y_line - 0.35, str(val), fontproperties=thai_font_small,
                ha="center", va="top", color="#475569", zorder=3)

    # Render intervals or rays
    intervals = params.get("intervals", [])
    for interval in intervals:
        color = interval.get("color", "#2563eb")
        if "direction" in interval:
            # Ray
            start_x = float(interval.get("from", 0))
            direction = interval.get("direction", "right")
            inc = interval.get("include_from", True)
            end_x = n_max + 0.6 if direction == "right" else n_min - 0.6
            
            # Highlighted ray line
            ax.plot([start_x, end_x], [y_line, y_line], color=color, linewidth=3.5, zorder=4)
            # Arrow
            ray_arrow = dict(arrowstyle="-|>", color=color, lw=2.5, mutation_scale=14)
            ax.annotate("", xy=(end_x, y_line), xytext=(end_x - (0.2 if direction == "right" else -0.2), y_line),
                        arrowprops=ray_arrow, zorder=4)
            # Circle
            face = color if inc else "white"
            ax.scatter([start_x], [y_line], facecolor=face, edgecolor=color, s=70, linewidth=2.0, zorder=5)
        else:
            # Segment between from and to
            from_x = float(interval.get("from", n_min))
            to_x = float(interval.get("to", n_max))
            inc_from = interval.get("include_from", True)
            inc_to = interval.get("include_to", True)

            ax.plot([from_x, to_x], [y_line, y_line], color=color, linewidth=3.5, zorder=4)
            ax.scatter([from_x], [y_line], facecolor=color if inc_from else "white",
                       edgecolor=color, s=70, linewidth=2.0, zorder=5)
            ax.scatter([to_x], [y_line], facecolor=color if inc_to else "white",
                       edgecolor=color, s=70, linewidth=2.0, zorder=5)

    # Render individual highlighted points
    points = params.get("points", [])
    for pt in points:
        px = float(pt.get("x", 0))
        label = pt.get("label", "")
        closed = pt.get("closed", True)
        color = pt.get("color", "#dc2626")
        
        face = color if closed else "white"
        ax.scatter([px], [y_line], facecolor=face, edgecolor=color, s=75, linewidth=2.0, zorder=5)
        if label:
            ax.text(px, y_line + 0.35, label, fontproperties=thai_font_bold,
                    ha="center", va="bottom", bbox=LABEL_BBOX, zorder=6)

    title = params.get("title")
    if title:
        ax.text((n_min + n_max) / 2, 0.95, title, fontproperties=thai_font_bold,
                ha="center", va="bottom", color="#0f172a")

    return fig_to_buffer(fig)


# ==============================================================================
# Diagram Dispatcher
# ==============================================================================

def create_diagram(diagram: dict) -> io.BytesIO:
    """
    Dispatches diagram generation based on diagram['type'].
    Returns an in-memory BytesIO buffer of the rendered PNG, or None if type unsupported or error occurs.
    """
    if not isinstance(diagram, dict):
        return None

    diagram_type = diagram.get("type", "").lower()
    params = diagram.get("params", {})

    try:
        if diagram_type == "coordinate_graph":
            return create_coordinate_graph(params)
        elif diagram_type == "function_graph":
            return create_function_graph(params)
        elif diagram_type == "geometry":
            return create_geometry_diagram(params)
        elif diagram_type == "bar_chart":
            return create_bar_chart(params)
        elif diagram_type == "number_line":
            return create_number_line(params)
        else:
            logger.warning(f"Unsupported diagram type: {diagram_type}")
            return None
    except Exception as e:
        logger.error(f"Failed to render diagram of type '{diagram_type}': {e}", exc_info=True)
        return None
