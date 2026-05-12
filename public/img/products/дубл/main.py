from PIL import Image
import os

# Путь к исходному изображению
input_image_path = "img.png"  # замените на путь к вашему файлу
output_dir = "duplicates"         # папка для сохранения копий

# Количество копий
num_copies = 100

# Создаём папку для копий, если её нет
os.makedirs(output_dir, exist_ok=True)

# Открываем исходное изображение
with Image.open(input_image_path) as img:
    for i in range(1, num_copies + 1):
        output_path = os.path.join(output_dir, f"product{i}.png")
        img.save(output_path)
        print(f"Сохранено: {output_path}")