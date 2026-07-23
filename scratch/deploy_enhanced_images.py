import shutil
import os

brain_dir = r"C:\Users\XIA\.gemini\antigravity\brain\0b503884-2cf6-4ef9-9e24-a38a92823ebb"
target_base = r"c:\Users\XIA\Documents\xampp\htdocs\emperor_hotel\public\assets\images\rooms"

mappings = [
    # Imperial Deluxe
    ("imperial_deluxe_hero_1784825320673.jpg", os.path.join(target_base, "imperial-deluxe", "hero.jpg")),
    ("imperial_deluxe_1_1784825253144.jpg", os.path.join(target_base, "imperial-deluxe", "carousel", "1.jpg")),
    ("imperial_deluxe_2_1784825275157.jpg", os.path.join(target_base, "imperial-deluxe", "carousel", "2.jpg")),
    ("imperial_deluxe_3_1784825295603.jpg", os.path.join(target_base, "imperial-deluxe", "carousel", "3.jpg")),

    # Royal Executive
    ("royal_executive_hero_1784825405359.jpg", os.path.join(target_base, "royal-executive-suite", "hero.jpg")),
    ("royal_executive_1_1784825342186.jpg", os.path.join(target_base, "royal-executive-suite", "carousel", "1.jpg")),
    ("royal_executive_2_1784825361657.jpg", os.path.join(target_base, "royal-executive-suite", "carousel", "2.jpg")),
    ("royal_executive_3_1784825381076.jpg", os.path.join(target_base, "royal-executive-suite", "carousel", "3.jpg")),

    # Emperor Presidential
    ("emperor_presidential_hero_1784825492389.jpg", os.path.join(target_base, "emperor-presidential-suite", "hero.jpg")),
    ("emperor_presidential_1_1784825427263.jpg", os.path.join(target_base, "emperor-presidential-suite", "carousel", "1.jpg")),
    ("emperor_presidential_2_1784825450608.jpg", os.path.join(target_base, "emperor-presidential-suite", "carousel", "2.jpg")),
    ("emperor_presidential_3_1784825469425.jpg", os.path.join(target_base, "emperor-presidential-suite", "carousel", "3.jpg")),
]

for src_name, dst_path in mappings:
    src_path = os.path.join(brain_dir, src_name)
    os.makedirs(os.path.dirname(dst_path), exist_ok=True)
    shutil.copy2(src_path, dst_path)
    print(f"Copied {src_name} -> {dst_path}")

print("All enhanced suite photos and hero images updated successfully!")
