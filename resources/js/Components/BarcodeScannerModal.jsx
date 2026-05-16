import { useEffect, useRef, useState } from 'react';
import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode';

const SCANNER_ID = 'barcode-scanner-viewport';

/**
 * Модальное окно: сканирование QR / штрихкода с камеры.
 * onScan(decodedText) — один успешный результат, затем сканер останавливается.
 */
export default function BarcodeScannerModal({ open, onClose, onScan, title = 'Сканировать код' }) {
    const scannerRef = useRef(null);
    const [error, setError] = useState('');
    const [starting, setStarting] = useState(false);
    const handledRef = useRef(false);
    const onScanRef = useRef(onScan);
    onScanRef.current = onScan;

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        handledRef.current = false;
        setError('');
        setStarting(true);

        const html5QrCode = new Html5Qrcode(SCANNER_ID, { verbose: false });
        scannerRef.current = html5QrCode;

        const config = {
            fps: 12,
            qrbox: { width: 280, height: 160 },
            aspectRatio: 1.5,
            formatsToSupport: [
                Html5QrcodeSupportedFormats.QR_CODE,
                Html5QrcodeSupportedFormats.CODE_128,
                Html5QrcodeSupportedFormats.CODE_39,
                Html5QrcodeSupportedFormats.CODE_93,
                Html5QrcodeSupportedFormats.EAN_13,
                Html5QrcodeSupportedFormats.EAN_8,
                Html5QrcodeSupportedFormats.UPC_A,
                Html5QrcodeSupportedFormats.UPC_E,
                Html5QrcodeSupportedFormats.ITF,
            ],
        };

        let mounted = true;

        html5QrCode
            .start(
                { facingMode: 'environment' },
                config,
                (decodedText) => {
                    if (handledRef.current || !mounted) {
                        return;
                    }
                    handledRef.current = true;
                    const value = String(decodedText).trim();
                    if (value) {
                        onScanRef.current?.(value);
                    }
                },
                () => {
                    /* пустые кадры — норма */
                }
            )
            .then(() => {
                if (mounted) {
                    setStarting(false);
                }
            })
            .catch((err) => {
                if (!mounted) {
                    return;
                }
                setStarting(false);
                const msg = err?.message || String(err);
                if (msg.includes('NotAllowed') || msg.includes('Permission')) {
                    setError('Нет доступа к камере. Разрешите камеру в настройках браузера.');
                } else if (msg.includes('NotFound') || msg.includes('no camera')) {
                    setError('Камера не найдена на этом устройстве.');
                } else {
                    setError('Не удалось запустить камеру. Попробуйте другой браузер или введите код вручную.');
                }
            });

        return () => {
            mounted = false;
            const instance = scannerRef.current;
            scannerRef.current = null;
            if (instance) {
                instance
                    .stop()
                    .then(() => instance.clear())
                    .catch(() => {});
            }
        };
    }, [open]);

    if (!open) {
        return null;
    }

    return (
        <div className="barcode-scanner-modal" role="dialog" aria-modal="true" onClick={onClose}>
            <div className="barcode-scanner-modal__panel" onClick={(e) => e.stopPropagation()}>
                <div className="barcode-scanner-modal__head">
                    <h2 className="barcode-scanner-modal__title">{title}</h2>
                    <button
                        type="button"
                        className="barcode-scanner-modal__close"
                        onClick={onClose}
                        aria-label="Закрыть"
                    >
                        ×
                    </button>
                </div>
                <p className="barcode-scanner-modal__hint">
                    Наведите на QR-код или штрихкод заказа / суточный код покупателя
                </p>
                {error ? <p className="barcode-scanner-modal__error">{error}</p> : null}
                {starting && !error ? (
                    <p className="barcode-scanner-modal__hint">Запуск камеры…</p>
                ) : null}
                <div id={SCANNER_ID} className="barcode-scanner-modal__viewport" />
                <button type="button" className="adm-action-btn barcode-scanner-modal__cancel" onClick={onClose}>
                    Отмена
                </button>
            </div>
        </div>
    );
}
