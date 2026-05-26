const MAX_AVATAR_BYTES = 10 * 1024 * 1024;
const MAX_DIMENSION = 1600;
const JPEG_QUALITY = 0.88;

function loadImageFromFile(file) {
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file);
    const img = new Image();
    img.onload = () => {
      URL.revokeObjectURL(url);
      resolve(img);
    };
    img.onerror = () => {
      URL.revokeObjectURL(url);
      reject(new Error('Не удалось прочитать изображение. Попробуйте JPG или PNG.'));
    };
    img.src = url;
  });
}

function canvasToBlob(canvas, type, quality) {
  return new Promise((resolve, reject) => {
    canvas.toBlob(
      (blob) => {
        if (!blob) {
          reject(new Error('Не удалось обработать фото.'));
          return;
        }
        resolve(blob);
      },
      type,
      quality,
    );
  });
}

/**
 * Сжимает фото с телефона до JPEG, чтобы загрузка проходила стабильно.
 */
export async function compressAvatarImage(file) {
  if (!file) {
    throw new Error('Файл не выбран.');
  }

  if (!file.type.startsWith('image/')) {
    throw new Error('Можно загрузить только изображение (JPG, PNG, WEBP).');
  }

  if (file.size > MAX_AVATAR_BYTES) {
    throw new Error('Фото слишком большое (больше 10 МБ). Выберите другое или сделайте снимок с меньшим качеством.');
  }

  const img = await loadImageFromFile(file);
  const scale = Math.min(1, MAX_DIMENSION / Math.max(img.width, img.height));
  const width = Math.max(1, Math.round(img.width * scale));
  const height = Math.max(1, Math.round(img.height * scale));

  const canvas = document.createElement('canvas');
  canvas.width = width;
  canvas.height = height;
  const ctx = canvas.getContext('2d');
  if (!ctx) {
    throw new Error('Браузер не поддерживает обработку изображений.');
  }

  ctx.drawImage(img, 0, 0, width, height);

  let blob = await canvasToBlob(canvas, 'image/jpeg', JPEG_QUALITY);
  let quality = JPEG_QUALITY;

  while (blob.size > 2 * 1024 * 1024 && quality > 0.5) {
    quality -= 0.08;
    blob = await canvasToBlob(canvas, 'image/jpeg', quality);
  }

  if (blob.size > MAX_AVATAR_BYTES) {
    throw new Error('Не удалось уменьшить фото до допустимого размера. Попробуйте другой снимок.');
  }

  const baseName = (file.name || 'avatar').replace(/\.[^.]+$/, '');
  return new File([blob], `${baseName}.jpg`, { type: 'image/jpeg', lastModified: Date.now() });
}

export function formatFileSize(bytes) {
  if (!bytes) return '0 Б';
  if (bytes < 1024) return `${bytes} Б`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} КБ`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} МБ`;
}
