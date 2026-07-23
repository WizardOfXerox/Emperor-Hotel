import os
import subprocess
import sys

sys.stdout.reconfigure(encoding='utf-8')

project_dir = r"c:\Users\XIA\Documents\xampp\htdocs\emperor_hotel"
php_files = []

for root, dirs, files in os.walk(project_dir):
    if "vendor" in root or ".git" in root or "scratch" in root:
        continue
    for file in files:
        if file.endswith(".php"):
            php_files.append(os.path.join(root, file))

print(f"Found {len(php_files)} PHP files to lint...\n")

errors = []
success_count = 0

for file_path in sorted(php_files):
    rel_path = os.path.relpath(file_path, project_dir)
    res = subprocess.run(["php", "-l", file_path], capture_output=True, text=True)
    if res.returncode != 0:
        errors.append((rel_path, res.stdout.strip() or res.stderr.strip()))
        print(f"[FAIL] ERROR in {rel_path}:\n{res.stdout.strip()}")
    else:
        success_count += 1
        print(f"[OK] {rel_path}")

print("\n" + "="*50)
if errors:
    print(f"FAILED: {len(errors)} error(s) found in {len(php_files)} PHP files:")
    for rel_path, err in errors:
        print(f"  - {rel_path}: {err}")
else:
    print(f"SUCCESS: All {success_count} PHP files passed linting with zero syntax errors!")
print("="*50)
