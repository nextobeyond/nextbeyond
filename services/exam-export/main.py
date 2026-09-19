#!/usr/bin/env python3
"""
CLI entry point for Exam DOCX Export Engine.
Reads normalized exam JSON from stdin (or --input), renders diagrams and DOCX,
and writes the resulting binary to stdout (or --output).
"""

import sys
import os
import json
import argparse
import logging
from document_generator import generate_exam_docx
from font_manager import ensure_sarabun_available

# Configure root logger to output to stderr
logging.basicConfig(
    stream=sys.stderr,
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s"
)
logger = logging.getLogger("exam_export_cli")


def main():
    parser = argparse.ArgumentParser(description="Exam DOCX Export Engine CLI")
    parser.add_argument("--input", "-i", type=str, default=None,
                        help="Path to input JSON file (default: read from stdin)")
    parser.add_argument("--output", "-o", type=str, default=None,
                        help="Path to output .docx file (default: write to stdout)")
    parser.add_argument("--ensure-fonts", action="store_true",
                        help="Download and verify font availability, then exit")
    args = parser.parse_args()

    # Pre-fetch fonts if requested
    if args.ensure_fonts:
        ensure_sarabun_available()
        sys.stderr.write("Fonts verified successfully.\n")
        sys.exit(0)

    # 1. Read input JSON
    try:
        if args.input:
            with open(args.input, "r", encoding="utf-8") as f:
                payload = json.load(f)
        else:
            payload = json.load(sys.stdin)
    except Exception as e:
        err_msg = json.dumps({"error": f"Failed to parse input JSON: {str(e)}"}, ensure_ascii=False)
        sys.stderr.write(err_msg + "\n")
        sys.exit(1)

    if not isinstance(payload, dict):
        err_msg = json.dumps({"error": "Invalid exam payload: expected JSON object"}, ensure_ascii=False)
        sys.stderr.write(err_msg + "\n")
        sys.exit(1)

    # 2. Generate DOCX
    try:
        docx_buffer = generate_exam_docx(payload)
        docx_bytes = docx_buffer.getvalue()
    except Exception as e:
        logger.error(f"Failed to generate exam DOCX: {e}", exc_info=True)
        err_msg = json.dumps({"error": f"DOCX generation failed: {str(e)}"}, ensure_ascii=False)
        sys.stderr.write(err_msg + "\n")
        sys.exit(1)

    # 3. Output binary
    try:
        if args.output:
            with open(args.output, "wb") as f:
                f.write(docx_bytes)
        else:
            sys.stdout.buffer.write(docx_bytes)
            sys.stdout.buffer.flush()
    except Exception as e:
        logger.error(f"Failed to write output: {e}", exc_info=True)
        err_msg = json.dumps({"error": f"Failed to write output: {str(e)}"}, ensure_ascii=False)
        sys.stderr.write(err_msg + "\n")
        sys.exit(1)


if __name__ == "__main__":
    main()
