"""
Document Generator for Exam DOCX Export Engine.
Uses python-docx to generate professionally formatted Microsoft Word (.docx) exam papers.
Ensures correct Word XML font mapping for Thai typography (Sarabun), A4 layout,
clean question spacing, centered vector diagrams, and teacher answer keys.
"""

import io
import logging
from typing import List, Dict, Any, Optional
import docx
from docx.shared import Inches, Pt, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.table import WD_TABLE_ALIGNMENT, WD_ALIGN_VERTICAL
from docx.oxml import OxmlElement, parse_xml
from docx.oxml.ns import qn, nsdecls

from diagram_renderer import create_diagram

logger = logging.getLogger("document_generator")
if not logger.handlers:
    logging.basicConfig(level=logging.INFO)

FONT_NAME = "Sarabun"


# ==============================================================================
# Helper: Word XML Font & Typography Helpers
# ==============================================================================

def set_run_font(run, font_name: str = FONT_NAME, size_pt: Optional[float] = None,
                 bold: Optional[bool] = None, italic: Optional[bool] = None,
                 color_rgb: Optional[RGBColor] = None):
    """
    Applies font properties and configures Word XML rFonts (ascii, hAnsi, eastAsia, cs)
    so Thai and mixed-script text renders with the specified font (Sarabun).
    """
    run.font.name = font_name
    if size_pt is not None:
        run.font.size = Pt(size_pt)
    if bold is not None:
        run.bold = bold
    if italic is not None:
        run.italic = italic
    if color_rgb is not None:
        run.font.color.rgb = color_rgb

    # Apply font name across all script types in Word XML
    rPr = run._element.get_or_add_rPr()
    rFonts = rPr.get_or_add_rFonts()
    rFonts.set(qn("w:ascii"), font_name)
    rFonts.set(qn("w:hAnsi"), font_name)
    rFonts.set(qn("w:eastAsia"), font_name)
    rFonts.set(qn("w:cs"), font_name)


def set_cell_margins(cell, top=100, bottom=100, left=150, right=150):
    """Sets internal padding on a table cell (values in twips, 20 twips = 1 pt)."""
    tcPr = cell._tc.get_or_add_tcPr()
    tcMar = OxmlElement('w:tcMar')
    for m, val in (('w:top', top), ('w:bottom', bottom), ('w:left', left), ('w:right', right)):
        node = OxmlElement(m)
        node.set(qn('w:w'), str(val))
        node.set(qn('w:type'), 'dxa')
        tcMar.append(node)
    tcPr.append(tcMar)


def set_cell_background(cell, fill_hex: str):
    """Sets background color of a table cell."""
    tcPr = cell._tc.get_or_add_tcPr()
    shd = parse_xml(f'<w:shd {nsdecls("w")} w:fill="{fill_hex}"/>')
    tcPr.append(shd)


def set_table_borders(table, color="D1D5DB", sz="4", val="single"):
    """Applies subtle borders to a table."""
    tblPr = table._tbl.tblPr
    borders = parse_xml(
        f'<w:tblBorders {nsdecls("w")}>'
        f'  <w:top w:val="{val}" w:sz="{sz}" w:space="0" w:color="{color}"/>'
        f'  <w:left w:val="none"/>'
        f'  <w:bottom w:val="{val}" w:sz="{sz}" w:space="0" w:color="{color}"/>'
        f'  <w:right w:val="none"/>'
        f'  <w:insideH w:val="{val}" w:sz="{sz}" w:space="0" w:color="{color}"/>'
        f'  <w:insideV w:val="none"/>'
        f'</w:tblBorders>'
    )
    tblPr.append(borders)


# ==============================================================================
# Exam DOCX Document Generator
# ==============================================================================

class ExamDocxGenerator:
    """Generates a complete Word document from normalized exam data."""

    def __init__(self, exam_data: Dict[str, Any]):
        self.exam_data = exam_data
        self.doc = docx.Document()
        self.options = exam_data.get("options", {})
        
        # Options
        self.doc_type = self.options.get("doc_type", "student").lower()  # 'student' or 'teacher'
        self.is_teacher = self.doc_type == "teacher"
        self.show_title = self.options.get("show_title", True)
        self.show_diagrams = self.options.get("show_diagrams", True)
        self.show_answers = self.is_teacher and self.options.get("show_answers", True)
        self.show_explanations = self.is_teacher and self.options.get("show_explanations", True)

        self._configure_document()

    def _configure_document(self):
        """Sets A4 page size, 0.75 in margins, and default Normal style."""
        for section in self.doc.sections:
            # A4 page dimensions
            section.page_width = Inches(8.27)
            section.page_height = Inches(11.69)
            
            # Margins: 0.75 inch
            section.top_margin = Inches(0.75)
            section.bottom_margin = Inches(0.75)
            section.left_margin = Inches(0.75)
            section.right_margin = Inches(0.75)

        # Configure Normal style
        normal_style = self.doc.styles['Normal']
        normal_style.font.name = FONT_NAME
        normal_style.font.size = Pt(14)
        normal_style.font.color.rgb = RGBColor(0x11, 0x18, 0x27)
        normal_style.paragraph_format.line_spacing = 1.15
        normal_style.paragraph_format.space_after = Pt(4)
        normal_style.paragraph_format.space_before = Pt(0)

    def generate(self) -> io.BytesIO:
        """Assembles the entire document and returns an in-memory BytesIO buffer."""
        # 1. Header / Title Block
        if self.show_title:
            self._render_header_block()

        # 2. Render Items (Headers, Passages, Questions)
        items = self.exam_data.get("items", [])
        question_counter = 0

        for item in items:
            item_type = item.get("type", "question")
            if item_type == "header":
                self._render_section_heading(item)
            elif item_type == "passage":
                self._render_passage(item)
            elif item_type == "question":
                question_counter += 1
                self._render_question(item, fallback_number=question_counter)

        # 3. Teacher Answer Key Section (if teacher version and enabled)
        if self.show_answers:
            self._render_answer_key_section(items)

        # Save to in-memory buffer
        output_buffer = io.BytesIO()
        self.doc.save(output_buffer)
        output_buffer.seek(0)
        return output_buffer

    def _render_header_block(self):
        """Renders the exam title, subject/grade meta, and student name field."""
        title = self.exam_data.get("title", "แบบทดสอบ")
        subtitle = self.exam_data.get("subtitle", "")
        subject = self.exam_data.get("subject", "")
        level = self.exam_data.get("level", "")
        time_limit = self.exam_data.get("time_limit_minutes")

        # Document Title
        p_title = self.doc.add_paragraph()
        p_title.alignment = WD_ALIGN_PARAGRAPH.CENTER
        p_title.paragraph_format.space_before = Pt(0)
        p_title.paragraph_format.space_after = Pt(2)
        p_title.paragraph_format.keep_with_next = True
        run_title = p_title.add_run(title)
        set_run_font(run_title, font_name=FONT_NAME, size_pt=20, bold=True, color_rgb=RGBColor(0x0F, 0x17, 0x2A))

        # Teacher badge if teacher version
        if self.is_teacher:
            p_badge = self.doc.add_paragraph()
            p_badge.alignment = WD_ALIGN_PARAGRAPH.CENTER
            p_badge.paragraph_format.space_after = Pt(4)
            p_badge.paragraph_format.keep_with_next = True
            run_badge = p_badge.add_run("【 ฉบับครูผู้สอน / มีเฉลยและคำอธิบาย 】")
            set_run_font(run_badge, font_name=FONT_NAME, size_pt=12, bold=True, color_rgb=RGBColor(0xDC, 0x26, 0x26))

        # Subtitle
        if subtitle:
            p_sub = self.doc.add_paragraph()
            p_sub.alignment = WD_ALIGN_PARAGRAPH.CENTER
            p_sub.paragraph_format.space_after = Pt(4)
            p_sub.paragraph_format.keep_with_next = True
            run_sub = p_sub.add_run(subtitle)
            set_run_font(run_sub, font_name=FONT_NAME, size_pt=14, italic=True, color_rgb=RGBColor(0x47, 0x55, 0x69))

        # Meta info line
        meta_parts = []
        if subject:
            meta_parts.append(f"วิชา: {subject}")
        if level:
            meta_parts.append(f"ระดับชั้น: {level}")
        if time_limit:
            meta_parts.append(f"เวลา: {time_limit} นาที")
        
        if meta_parts:
            p_meta = self.doc.add_paragraph()
            p_meta.alignment = WD_ALIGN_PARAGRAPH.CENTER
            p_meta.paragraph_format.space_after = Pt(8)
            p_meta.paragraph_format.keep_with_next = True
            run_meta = p_meta.add_run("   |   ".join(meta_parts))
            set_run_font(run_meta, font_name=FONT_NAME, size_pt=12, bold=True, color_rgb=RGBColor(0x33, 0x41, 0x55))

        # Student Name / Classroom Header (for printed test papers)
        if not self.is_teacher:
            p_name = self.doc.add_paragraph()
            p_name.alignment = WD_ALIGN_PARAGRAPH.LEFT
            p_name.paragraph_format.space_before = Pt(4)
            p_name.paragraph_format.space_after = Pt(14)
            p_name.paragraph_format.keep_with_next = True
            
            run_name = p_name.add_run("ชื่อ-นามสกุล: ____________________________________________   เลขที่: _________   ห้อง: _________")
            set_run_font(run_name, font_name=FONT_NAME, size_pt=12, color_rgb=RGBColor(0x47, 0x55, 0x69))

        # Divider line
        p_div = self.doc.add_paragraph()
        p_div.paragraph_format.space_after = Pt(10)
        p_div.paragraph_format.keep_with_next = True
        run_div = p_div.add_run("―" * 52)
        set_run_font(run_div, font_name=FONT_NAME, size_pt=10, color_rgb=RGBColor(0xCB, 0xD5, 0xE1))

    def _render_section_heading(self, header_item: Dict[str, Any]):
        """Renders section header with keep_with_next to prevent orphan headers."""
        text = header_item.get("text", "")
        if not text:
            return

        p = self.doc.add_paragraph()
        p.paragraph_format.space_before = Pt(14)
        p.paragraph_format.space_after = Pt(6)
        p.paragraph_format.keep_with_next = True

        run = p.add_run(text)
        set_run_font(run, font_name=FONT_NAME, size_pt=16, bold=True, color_rgb=RGBColor(0x1E, 0x29, 0x3B))

    def _render_passage(self, passage_item: Dict[str, Any]):
        """Renders a reading passage or context paragraph."""
        text = passage_item.get("text", "")
        if not text:
            return

        p = self.doc.add_paragraph()
        p.paragraph_format.space_before = Pt(8)
        p.paragraph_format.space_after = Pt(8)
        p.paragraph_format.left_indent = Inches(0.2)
        p.paragraph_format.right_indent = Inches(0.2)
        p.paragraph_format.keep_with_next = True

        run = p.add_run(text)
        set_run_font(run, font_name=FONT_NAME, size_pt=13, italic=True, color_rgb=RGBColor(0x33, 0x41, 0x55))

    def _render_question(self, q: Dict[str, Any], fallback_number: int):
        """Renders question number, text, server-side diagram (if any), and choices."""
        num = q.get("number", fallback_number)
        text = q.get("text", "")
        choices = q.get("choices", [])
        diagram = q.get("diagram")

        # 1. Question Paragraph
        p_q = self.doc.add_paragraph()
        p_q.paragraph_format.space_before = Pt(10)
        p_q.paragraph_format.space_after = Pt(4)
        p_q.paragraph_format.keep_with_next = True

        # Question Number in bold
        run_num = p_q.add_run(f"{num}. ")
        set_run_font(run_num, font_name=FONT_NAME, size_pt=14, bold=True, color_rgb=RGBColor(0x0F, 0x17, 0x2A))

        # Question Text
        run_text = p_q.add_run(text)
        set_run_font(run_text, font_name=FONT_NAME, size_pt=14, bold=False, color_rgb=RGBColor(0x0F, 0x17, 0x2A))

        # 2. Render Diagram if present
        if self.show_diagrams and diagram:
            self._render_question_diagram(diagram)

        # 3. Render Choices
        self._render_choices(choices)

    def _render_question_diagram(self, diagram: Dict[str, Any]):
        """Generates diagram using diagram_renderer and embeds it centered into the document."""
        try:
            buf = create_diagram(diagram)
            if buf:
                p_img = self.doc.add_paragraph()
                p_img.alignment = WD_ALIGN_PARAGRAPH.CENTER
                p_img.paragraph_format.space_before = Pt(6)
                p_img.paragraph_format.space_after = Pt(6)
                p_img.paragraph_format.keep_with_next = True

                # Determine display width based on diagram type
                dtype = diagram.get("type", "")
                if dtype == "number_line":
                    width = Inches(4.5)
                elif dtype in ("coordinate_graph", "function_graph"):
                    width = Inches(3.6)
                elif dtype == "geometry":
                    width = Inches(3.5)
                elif dtype == "bar_chart":
                    width = Inches(4.2)
                else:
                    width = Inches(3.8)

                p_img.add_run().add_picture(buf, width=width)
            else:
                self._render_diagram_fallback()
        except Exception as e:
            logger.warning(f"Error embedding diagram: {e}")
            self._render_diagram_fallback()

    def _render_diagram_fallback(self):
        """Displays a clean placeholder if diagram generation fails."""
        p_fb = self.doc.add_paragraph()
        p_fb.alignment = WD_ALIGN_PARAGRAPH.CENTER
        p_fb.paragraph_format.space_before = Pt(4)
        p_fb.paragraph_format.space_after = Pt(4)
        p_fb.paragraph_format.keep_with_next = True
        run_fb = p_fb.add_run("[ไม่สามารถสร้างรูปประกอบข้อนี้ได้]")
        set_run_font(run_fb, font_name=FONT_NAME, size_pt=11, italic=True, color_rgb=RGBColor(0x94, 0xA3, 0xB8))

    def _render_choices(self, choices: List[str]):
        """Renders multiple-choice options with uniform left indentation."""
        if not choices:
            return

        choice_prefixes = ["A. ", "B. ", "C. ", "D. ", "E. ", "F. "]

        for idx, choice in enumerate(choices):
            choice_text = str(choice).strip()
            
            # Check if choice already begins with A., B., 1., etc.
            has_prefix = False
            for pfx in choice_prefixes:
                if choice_text.startswith(pfx) or choice_text.startswith(pfx.replace(". ", ") ")):
                    has_prefix = True
                    break

            prefix = "" if has_prefix else (choice_prefixes[idx] if idx < len(choice_prefixes) else f"{idx + 1}. ")

            p_choice = self.doc.add_paragraph()
            p_choice.paragraph_format.left_indent = Inches(0.28)
            p_choice.paragraph_format.space_before = Pt(1)
            p_choice.paragraph_format.space_after = Pt(3)

            # Keep choices together with the question
            if idx < len(choices) - 1:
                p_choice.paragraph_format.keep_with_next = True

            if prefix:
                run_pfx = p_choice.add_run(prefix)
                set_run_font(run_pfx, font_name=FONT_NAME, size_pt=14, bold=True, color_rgb=RGBColor(0x1E, 0x29, 0x3B))

            run_txt = p_choice.add_run(choice_text)
            set_run_font(run_txt, font_name=FONT_NAME, size_pt=14, bold=False, color_rgb=RGBColor(0x33, 0x41, 0x55))

    def _render_answer_key_section(self, items: List[Dict[str, Any]]):
        """Renders the teacher's Answer Key and Explanation section on a new page."""
        # Page break before answer key
        self.doc.add_page_break()

        p_h = self.doc.add_paragraph()
        p_h.paragraph_format.space_before = Pt(12)
        p_h.paragraph_format.space_after = Pt(8)
        p_h.paragraph_format.keep_with_next = True
        run_h = p_h.add_run("เฉลยและคำอธิบายคำตอบ")
        set_run_font(run_h, font_name=FONT_NAME, size_pt=18, bold=True, color_rgb=RGBColor(0x0F, 0x17, 0x2A))

        p_desc = self.doc.add_paragraph()
        p_desc.paragraph_format.space_after = Pt(14)
        run_desc = p_desc.add_run("ส่วนนี้สำหรับครูผู้สอนในการตรวจและเฉลยข้อสอบ")
        set_run_font(run_desc, font_name=FONT_NAME, size_pt=12, italic=True, color_rgb=RGBColor(0x64, 0x74, 0x8B))

        # Extract questions
        questions = [item for item in items if item.get("type", "question") == "question"]
        if not questions:
            return

        # Render clean answer table
        table = self.doc.add_table(rows=1, cols=3)
        table.alignment = WD_TABLE_ALIGNMENT.CENTER
        set_table_borders(table)

        # Header row
        hdr_cells = table.rows[0].cells
        hdr_cells[0].width = Inches(1.0)
        hdr_cells[1].width = Inches(1.2)
        hdr_cells[2].width = Inches(4.5)

        for cell in hdr_cells:
            set_cell_margins(cell, top=140, bottom=140, left=150, right=150)
            set_cell_background(cell, "F1F5F9")

        run0 = hdr_cells[0].paragraphs[0].add_run("ข้อที่")
        set_run_font(run0, font_name=FONT_NAME, size_pt=13, bold=True, color_rgb=RGBColor(0x1E, 0x29, 0x3B))
        hdr_cells[0].paragraphs[0].alignment = WD_ALIGN_PARAGRAPH.CENTER

        run1 = hdr_cells[1].paragraphs[0].add_run("เฉลย")
        set_run_font(run1, font_name=FONT_NAME, size_pt=13, bold=True, color_rgb=RGBColor(0x1E, 0x29, 0x3B))
        hdr_cells[1].paragraphs[0].alignment = WD_ALIGN_PARAGRAPH.CENTER

        run2 = hdr_cells[2].paragraphs[0].add_run("คำอธิบายเหตุผล")
        set_run_font(run2, font_name=FONT_NAME, size_pt=13, bold=True, color_rgb=RGBColor(0x1E, 0x29, 0x3B))

        # Data rows
        choice_letters = ["A", "B", "C", "D", "E", "F"]
        for idx, q in enumerate(questions):
            q_num = q.get("number", idx + 1)
            raw_answer = q.get("answer", "")
            
            # Format answer
            if isinstance(raw_answer, int) and 0 <= raw_answer < len(choice_letters):
                ans_str = choice_letters[raw_answer]
            elif isinstance(raw_answer, str) and raw_answer.isdigit() and 0 <= int(raw_answer) < len(choice_letters):
                ans_str = choice_letters[int(raw_answer)]
            else:
                ans_str = str(raw_answer) if raw_answer != "" else "—"

            explanation = q.get("explanation", "") or "—"

            row_cells = table.add_row().cells
            row_cells[0].width = Inches(1.0)
            row_cells[1].width = Inches(1.2)
            row_cells[2].width = Inches(4.5)

            for cell in row_cells:
                set_cell_margins(cell, top=120, bottom=120, left=150, right=150)

            # Alternating subtle row shading
            if idx % 2 == 1:
                for cell in row_cells:
                    set_cell_background(cell, "F8FAFC")

            # Question number
            r_num = row_cells[0].paragraphs[0].add_run(str(q_num))
            set_run_font(r_num, font_name=FONT_NAME, size_pt=13, bold=True, color_rgb=RGBColor(0x0F, 0x17, 0x2A))
            row_cells[0].paragraphs[0].alignment = WD_ALIGN_PARAGRAPH.CENTER

            # Answer
            r_ans = row_cells[1].paragraphs[0].add_run(ans_str)
            set_run_font(r_ans, font_name=FONT_NAME, size_pt=13, bold=True, color_rgb=RGBColor(0x16, 0xA3, 0x4A))
            row_cells[1].paragraphs[0].alignment = WD_ALIGN_PARAGRAPH.CENTER

            # Explanation
            if self.show_explanations:
                r_exp = row_cells[2].paragraphs[0].add_run(explanation)
                set_run_font(r_exp, font_name=FONT_NAME, size_pt=12, bold=False, color_rgb=RGBColor(0x33, 0x41, 0x55))
            else:
                r_exp = row_cells[2].paragraphs[0].add_run("—")
                set_run_font(r_exp, font_name=FONT_NAME, size_pt=12, color_rgb=RGBColor(0x94, 0xA3, 0xB8))


def generate_exam_docx(exam_data: Dict[str, Any]) -> io.BytesIO:
    """Convenience function to generate a DOCX from normalized exam data."""
    generator = ExamDocxGenerator(exam_data)
    return generator.generate()
