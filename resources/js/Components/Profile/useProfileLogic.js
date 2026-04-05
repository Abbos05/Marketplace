import { useState, useEffect } from 'react';
import { useForm, router } from '@inertiajs/react';

export default function useProfileLogic({ auth, initialNfts }) {
  const [message, setMessage] = useState(null);
  const [phoneTouched, setPhoneTouched] = useState(false);
  const [nfts, setNfts] = useState(initialNfts);

  // Flash message
  useEffect(() => {
    const flash = sessionStorage.getItem('flashMessage');
    if (flash) {
      setMessage(flash);
      sessionStorage.removeItem('flashMessage');
    }
  }, []);

  // Обновление NFT при смене фильтра
  useEffect(() => {
    setNfts(initialNfts);
  }, [initialNfts]);

  useEffect(() => {
    const unsubscribe = router.on('navigate', () => {
      if (window.location.search.includes('filter=')) {
        router.reload({ only: ['nfts'] });
      }
    });
    return unsubscribe;
  }, []);

  // Форматирование телефона
  const formatPhone = (value) => {
    const digits = value.replace(/\D/g, '').slice(0, 11);
    if (!digits) return '';
    let formatted = '+7 ';
    if (digits.length > 1) formatted += digits.substring(1, 4);
    if (digits.length > 4) formatted += ' ' + digits.substring(4, 7);
    if (digits.length > 7) formatted += ' ' + digits.substring(7, 9);
    if (digits.length > 9) formatted += ' ' + digits.substring(9, 11);
    return formatted;
  };

  const formatName = (fullName) => {
    const parts = fullName.split(' ');
    if (parts.length === 1) return parts[0];
    return parts.map((p, i) => (i === 0 ? p : p[0])).join(' ');
  };

  return {
    message,
    setMessage,
    phoneTouched,
    setPhoneTouched,
    nfts,
    formatPhone,
    formatName,
  };
}