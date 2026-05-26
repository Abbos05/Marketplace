Как добавить свои иконки PWA
=============================

1. Положите ОДИН файл с логотипом сюда:
   public/icons/icon-source.png
   (PNG, желательно не меньше 512×512 px)

2. Сгенерируйте все размеры для Chrome:
   python3 scripts/resize_pwa_icons.py

3. Скрипт перезапишет icon-72.png … icon-512.png квадратными версиями.
   Имена должны быть именно такими — manifest.json ссылается на них.

4. Очистите кэш в браузере:
   Chrome → F12 → Application → Service Workers → Unregister
   Application → Storage → Clear site data
   Или жёсткое обновление: Ctrl+Shift+R (Cmd+Shift+R на Mac)

Важно: нельзя просто переименовать один файл в icon-512.png —
реальный размер картинки должен совпадать с именем (512×512 px).
