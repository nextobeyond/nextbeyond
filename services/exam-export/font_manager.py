"""
Thai Font Manager for Matplotlib & Exam DOCX Export Engine.
Handles font priority resolution, system font detection, and auto-downloading Sarabun-Regular.ttf as a persistent fallback.
"""

import os
import sys
import logging
from pathlib import Path
import matplotlib.font_manager as fm

logger = logging.getLogger("font_manager")
if not logger.handlers:
    logging.basicConfig(level=logging.INFO)

FONTS_DIR = Path(__file__).resolve().parent / "fonts"
SARABUN_LOCAL_FILE = FONTS_DIR / "Sarabun-Regular.ttf"
SARABUN_BOLD_FILE = FONTS_DIR / "Sarabun-Bold.ttf"

# Official Google Fonts raw repository URLs for Sarabun
GOOGLE_FONTS_SARABUN_REGULAR = "https://raw.githubusercontent.com/google/fonts/main/ofl/sarabun/Sarabun-Regular.ttf"
GOOGLE_FONTS_SARABUN_BOLD = "https://raw.githubusercontent.com/google/fonts/main/ofl/sarabun/Sarabun-Bold.ttf"

FONT_PRIORITY = [
    "Sarabun",
    "TH Sarabun New",
    "Noto Sans Thai",
    "Tahoma",
    "Thonburi",
]

_CACHED_FONT_PATH = None
_CACHED_BOLD_FONT_PATH = None
_CACHED_FONT_NAME = None


def ensure_font_dir():
    """Ensure fonts directory exists."""
    FONTS_DIR.mkdir(parents=True, exist_ok=True)


def download_font_if_needed(url: str, target_path: Path) -> bool:
    """Downloads a font file from Google Fonts if not already cached locally."""
    if target_path.exists() and target_path.stat().st_size > 1000:
        return True
    try:
        ensure_font_dir()
        logger.info(f"Downloading font from {url} to {target_path}...")
        import requests
        resp = requests.get(url, timeout=15)
        if resp.status_code == 200 and len(resp.content) > 1000:
            with open(target_path, "wb") as f:
                f.write(resp.content)
            logger.info(f"Successfully downloaded and cached {target_path.name}")
            return True
        else:
            logger.warning(f"Failed to download font: HTTP {resp.status_code}")
    except Exception as e:
        logger.warning(f"Could not download font from {url}: {e}")
    return False


def find_system_thai_font():
    """Searches installed system fonts according to FONT_PRIORITY."""
    global _CACHED_FONT_PATH, _CACHED_FONT_NAME

    if _CACHED_FONT_PATH and os.path.exists(_CACHED_FONT_PATH):
        return _CACHED_FONT_PATH, _CACHED_FONT_NAME

    # 1. Check local cache first for absolute consistency
    if SARABUN_LOCAL_FILE.exists() and SARABUN_LOCAL_FILE.stat().st_size > 1000:
        _CACHED_FONT_PATH = str(SARABUN_LOCAL_FILE)
        _CACHED_FONT_NAME = "Sarabun"
        return _CACHED_FONT_PATH, _CACHED_FONT_NAME

    # 2. Search Matplotlib font manager's discovered system fonts
    for font_family in FONT_PRIORITY:
        lower_family = font_family.lower()
        for font in fm.fontManager.ttflist:
            if lower_family in font.name.lower():
                # Prefer standalone .ttf or .otf files over .ttc if possible
                if font.fname.lower().endswith(('.ttf', '.otf')):
                    _CACHED_FONT_PATH = font.fname
                    _CACHED_FONT_NAME = font.name
                    logger.info(f"Using system Thai font: {font.name} at {font.fname}")
                    return _CACHED_FONT_PATH, _CACHED_FONT_NAME

        # If only .ttc is found for the top candidate
        for font in fm.fontManager.ttflist:
            if lower_family in font.name.lower():
                _CACHED_FONT_PATH = font.fname
                _CACHED_FONT_NAME = font.name
                logger.info(f"Using system Thai font ({font.name}) at {font.fname}")
                return _CACHED_FONT_PATH, _CACHED_FONT_NAME

    # 3. If no system font found, download Sarabun from Google Fonts
    if download_font_if_needed(GOOGLE_FONTS_SARABUN_REGULAR, SARABUN_LOCAL_FILE):
        _CACHED_FONT_PATH = str(SARABUN_LOCAL_FILE)
        _CACHED_FONT_NAME = "Sarabun"
        fm.fontManager.addfont(_CACHED_FONT_PATH)
        return _CACHED_FONT_PATH, _CACHED_FONT_NAME

    # Fallback to generic sans-serif
    _CACHED_FONT_PATH = None
    _CACHED_FONT_NAME = "sans-serif"
    return None, "sans-serif"


def get_thai_font(size: int = 12, weight: str = "normal") -> fm.FontProperties:
    """
    Returns a FontProperties instance configured with a valid Thai font and specified size.
    Explicitly applied to every text, title, tick, legend, and annotation in Matplotlib.
    """
    font_path, font_name = find_system_thai_font()

    if weight in ("bold", "semibold", "heavy"):
        if SARABUN_BOLD_FILE.exists():
            return fm.FontProperties(fname=str(SARABUN_BOLD_FILE), size=size, weight=weight)
        if font_path and os.path.exists(font_path):
            return fm.FontProperties(fname=font_path, size=size, weight=weight)
        return fm.FontProperties(family=font_name, size=size, weight="bold")

    if font_path and os.path.exists(font_path):
        return fm.FontProperties(fname=font_path, size=size, weight=weight)
    return fm.FontProperties(family=font_name, size=size, weight=weight)


def ensure_sarabun_available():
    """Ensures Sarabun-Regular.ttf and Sarabun-Bold.ttf are cached locally for optimal Word and Matplotlib rendering."""
    download_font_if_needed(GOOGLE_FONTS_SARABUN_REGULAR, SARABUN_LOCAL_FILE)
    download_font_if_needed(GOOGLE_FONTS_SARABUN_BOLD, SARABUN_BOLD_FILE)
