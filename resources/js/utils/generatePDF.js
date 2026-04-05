// // resources/js/utils/generatePDF.js
// import jsPDF from 'jspdf';

// export const generatePDF = (tx) => {
//   const doc = new jsPDF('p', 'mm', 'a4');
//   const width = doc.internal.pageSize.getWidth();

//   // === ФОН ===
//   doc.setFillColor(10, 14, 26);
//   doc.rect(0, 0, width, 297, 'F');

//   // === ЗАГОЛОВОК ===
//   doc.setFont('helvetica', 'bold');
//   doc.setFontSize(22);
//   doc.setTextColor(255, 212, 56);
//   doc.text('CERTIFICATE OF TRANSACTION', width / 2, 35, { align: 'center' });

//   doc.setFont('helvetica', 'normal');
//   doc.setFontSize(12);
//   doc.setTextColor(200, 200, 200);
//   doc.text('AltChain — Official NFT Marketplace', width / 2, 45, { align: 'center' });

//   // === ДАННЫЕ (ЛАТИНИЦА + ЦИФРЫ) ===
//   const startY = 70;
//   doc.setTextColor(255, 255, 255);
//   doc.setFontSize(14);
//   let y = startY;

//   const addLine = (label, value) => {
//     doc.setFont('helvetica', 'bold');
//     doc.text(label, 20, y);
//     doc.setFont('helvetica', 'normal');
//     doc.text(value, 70, y);
//     y += 12;
//   };

//   addLine('Type:', tx.type === 'buy' ? 'Purchase' : 'Sale');
//   addLine('NFT:', tx.nft.title || 'Unknown');
//   addLine('ID:', `#${tx.nft.id}`);
//   addLine('Counterparty:', tx.counterparty.name);
//   addLine('Price:', `${tx.price} RUB`);
//   addLine('Date:', new Date(tx.created_at).toLocaleDateString('en-GB'));
//   addLine('TX ID:', `#TX${tx.id}`);

//   // === ПЕЧАТЬ ===
//   doc.setFontSize(16);
//   doc.setTextColor(255, 212, 56);
//   doc.text('VERIFIED', 20, y + 20);

//   // === QR ===
//   doc.setFillColor(255, 212, 56);
//   doc.rect(20, y + 30, 35, 35, 'F');
//   doc.setTextColor(0);
//   doc.setFontSize(8);
//   doc.text('QR', 37.5, y + 50, { align: 'center' });

//   // === НИЗ ===
//   doc.setDrawColor(255, 212, 56);
//   doc.line(20, 257, width - 20, 257);
//   doc.setFontSize(10);
//   doc.setTextColor(150, 150, 150);
//   doc.text('© 2025 AltChain', width / 2, 272, { align: 'center' });

//   // === СКАЧАТЬ ===
//   doc.save(`TX_${tx.id}.pdf`);
// };