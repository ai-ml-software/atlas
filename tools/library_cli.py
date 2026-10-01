"""Run library CLI operations, encoding file paths safely for CI3 URI parsing.

Example: python tools/library_cli.py --php /path/to/php report @reports/coverage.json en
"""
from pathlib import Path
import argparse
import base64
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser()
parser.add_argument('--php', default='php')
parser.add_argument('arguments', nargs=argparse.REMAINDER)
args = parser.parse_args()
arguments = [('b64:' + base64.urlsafe_b64encode(x[1:].encode()).decode().rstrip('=')) if x.startswith('@') else x for x in args.arguments]
if not arguments: parser.error('Provide a library command')
sys.exit(subprocess.call([args.php,str(ROOT/'index.php'),'ha_library',*arguments],cwd=ROOT))
