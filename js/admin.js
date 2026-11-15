/**
 * RiasKu Panel Admin - Logika Interaktif & Navigasi
 * Vanilla JavaScript - Tanpa Framework & Pustaka Eksternal
 */

(function () {
  'use strict';

  // =========================================================================
  // 1. STATE & DATA AWAL (KOSMETIK UMKM RIASKU)
  // =========================================================================

  const state = {
    currentRoute: 'ringkasan',
    greetingName: 'Admin',
    storeSettings: {
      storeName: 'RiasKu Official Store',
      whatsapp: '081234567890',
      originAddress: 'Jl. Rungkut Asri Timur No. 18',
      originCity: 'Surabaya',
      localShippingFee: 0,
      minStockThreshold: 5,
      paymentDeadlineHours: 24
    },
    products: [
      {
        id: 1,
        name: 'Glow Velvet Lip Tint',
        brand: 'RiasKu Glow',
        category: 'Bibir',
        modalPrice: 28000,
        retailPrice: 38000,
        packAvailable: true,
        packSize: 6,
        packPricePerPcs: 34000,
        active: true,
        canPreOrder: false,
        preOrderDays: 0,
        image: 'RiasKu Serif R App Icon.png',
        variants: [
          { name: '01 Rose Bloom', stock: 18, active: true },
          { name: '02 Berry Mauve', stock: 14, active: true },
          { name: '03 Peach Nude', stock: 10, active: true }
        ]
      },
      {
        id: 2,
        name: 'Dewy Cushion SPF 35 PA+++',
        brand: 'RiasKu Glow',
        category: 'Wajah',
        modalPrice: 72000,
        retailPrice: 85000,
        packAvailable: false,
        packSize: 0,
        packPricePerPcs: 0,
        active: true,
        canPreOrder: false,
        preOrderDays: 0,
        image: 'RiasKu Serif R App Icon.png',
        variants: [
          { name: '01 Light Sand', stock: 9, active: true },
          { name: '02 Warm Beige', stock: 6, active: true }
        ]
      },
      {
        id: 3,
        name: 'Gentle Sunscreen Gel 50ml',
        brand: 'RiasKu Care',
        category: 'Perawatan',
        modalPrice: 38000,
        retailPrice: 49000,
        packAvailable: true,
        packSize: 10,
        packPricePerPcs: 45000,
        active: true,
        canPreOrder: true,
        preOrderDays: 3,
        image: 'RiasKu Serif R App Icon.png',
        variants: [
          { name: 'Standar 50ml', stock: 0, active: true }
        ]
      },
      {
        id: 4,
        name: 'Hydrating Barrier Serum',
        brand: 'RiasKu Care',
        category: 'Perawatan',
        modalPrice: 55000,
        retailPrice: 68000,
        packAvailable: false,
        packSize: 0,
        packPricePerPcs: 0,
        active: false,
        canPreOrder: false,
        preOrderDays: 0,
        image: 'RiasKu Serif R App Icon.png',
        variants: [
          { name: 'Standar 30ml', stock: 0, active: false }
        ]
      },
      {
        id: 5,
        name: 'Matte Powder Foundation',
        brand: 'RiasKu Glow',
        category: 'Wajah',
        modalPrice: 42000,
        retailPrice: 55000,
        packAvailable: true,
        packSize: 6,
        packPricePerPcs: 50000,
        active: true,
        canPreOrder: false,
        preOrderDays: 0,
        image: 'RiasKu Serif R App Icon.png',
        variants: [
          { name: 'Natural Tan', stock: 8, active: true }
        ]
      }
    ],
    categories: [
      { id: 1, name: 'Bibir', count: 1, createdAt: '12 Okt 2026' },
      { id: 2, name: 'Wajah', count: 2, createdAt: '10 Okt 2026' },
      { id: 3, name: 'Perawatan', count: 2, createdAt: '15 Okt 2026' },
      { id: 4, name: 'Aksesoris', count: 0, createdAt: '28 Okt 2026' }
    ],
    reviews: [
      {
        id: 1,
        product: 'Glow Velvet Lip Tint - 01 Rose Bloom',
        reviewer: 'Dinda Permata',
        orderCode: 'RK-0012',
        rating: 5,
        comment: 'Teksturnya ringan sekali di bibir, warnanya natural dan tidak bikin kering sama sekali. Bakal repurchase!',
        photosCount: 2,
        date: '5 Nov 2026',
        status: 'Tampil',
        expanded: false
      },
      {
        id: 2,
        product: 'Dewy Cushion SPF 35 - 01 Light Sand',
        reviewer: 'Siti Nurhaliza',
        orderCode: 'RK-0010',
        rating: 5,
        comment: 'Coveragenya medium to high, finishnya dewy glowing sehat khas cewek korea. Pengiriman super cepat.',
        photosCount: 1,
        date: '4 Nov 2026',
        status: 'Tampil',
        expanded: false
      },
      {
        id: 3,
        product: 'Gentle Sunscreen Gel 50ml',
        reviewer: 'Anisa Rahmawati',
        orderCode: 'RK-0008',
        rating: 4,
        comment: 'Tidak ada whitecast sama sekali, cepat meresap. Cuma waktu pre-order nunggu sekitar 3 harian.',
        photosCount: 0,
        date: '2 Nov 2026',
        status: 'Tampil',
        expanded: false
      },
      {
        id: 4,
        product: 'Hydrating Barrier Serum',
        reviewer: 'Anonim',
        orderCode: 'RK-0003',
        rating: 2,
        comment: 'Kurang cocok di kulit saya yang sensitif kemerahan.',
        photosCount: 0,
        date: '25 Okt 2026',
        status: 'Disembunyikan',
        expanded: false
      }
    ],
    orders: [
      {
        id: 'RK-0014',
        recipient: 'Nadia Safitri',
        city: 'Surabaya',
        address: 'Jl. Manyar Kertoarjo No. 45, Gubeng',
        phone: '081398765432',
        itemCount: 2,
        total: 76000,
        subtotal: 76000,
        shippingFee: 0,
        courier: 'Diantar Penjual',
        service: 'Sameday Kurir Toko',
        estimate: 'Hari ini',
        trackingNumber: '',
        paymentMethod: 'COD (Uang Tunai)',
        paymentStatus: 'Menunggu',
        orderStatus: 'Diproses',
        date: '6 Nov 2026',
        paymentDeadline: '7 Nov 2026 10:00',
        paidAt: '-',
        items: [
          { product: 'Glow Velvet Lip Tint', variant: '01 Rose Bloom', type: 'Eceran', qty: 2, price: 38000, subtotal: 76000, isPreOrder: false }
        ]
      },
      {
        id: 'RK-0013',
        recipient: 'Mega Utami',
        city: 'Sidoarjo',
        address: 'Perum Taman Anggun Blok C2 No. 8, Waru',
        phone: '085712349988',
        itemCount: 1,
        total: 97000,
        subtotal: 85000,
        shippingFee: 12000,
        courier: 'J&T',
        service: 'EZ Regular',
        estimate: '1-2 hari',
        trackingNumber: 'JT9823487192',
        paymentMethod: 'QRIS',
        paymentStatus: 'Lunas',
        orderStatus: 'Dikirim',
        date: '5 Nov 2026',
        paymentDeadline: '5 Nov 2026 18:00',
        paidAt: '5 Nov 2026 14:15',
        items: [
          { product: 'Dewy Cushion SPF 35', variant: '02 Warm Beige', type: 'Eceran', qty: 1, price: 85000, subtotal: 85000, isPreOrder: false }
        ]
      },
      {
        id: 'RK-0012',
        recipient: 'Dinda Permata',
        city: 'Surabaya',
        address: 'Jl. Dharmawangsa Barat No. 12',
        phone: '082144556677',
        itemCount: 3,
        total: 125000,
        subtotal: 125000,
        shippingFee: 0,
        courier: 'Diantar Penjual',
        service: 'Reguler',
        estimate: 'Selesai',
        trackingNumber: '',
        paymentMethod: 'Transfer Bank BCA',
        paymentStatus: 'Lunas',
        orderStatus: 'Selesai',
        date: '4 Nov 2026',
        paymentDeadline: '4 Nov 2026 12:00',
        paidAt: '4 Nov 2026 10:20',
        items: [
          { product: 'Glow Velvet Lip Tint', variant: '01 Rose Bloom', type: 'Eceran', qty: 1, price: 38000, subtotal: 38000, isPreOrder: false },
          { product: 'Dewy Cushion SPF 35', variant: '01 Light Sand', type: 'Eceran', qty: 1, price: 85000, subtotal: 85000, isPreOrder: false }
        ]
      },
      {
        id: 'RK-0011',
        recipient: 'Rina Kusuma',
        city: 'Gresik',
        address: 'Jl. RA Kartini No. 88, Kebomas',
        phone: '081233445566',
        itemCount: 1,
        total: 61000,
        subtotal: 49000,
        shippingFee: 12000,
        courier: 'JNE',
        service: 'REG',
        estimate: '2-3 hari',
        trackingNumber: '',
        paymentMethod: 'Transfer Bank Mandiri',
        paymentStatus: 'Menunggu',
        orderStatus: 'Menunggu Pembayaran',
        date: '6 Nov 2026',
        paymentDeadline: '7 Nov 2026 09:00',
        paidAt: '-',
        items: [
          { product: 'Gentle Sunscreen Gel 50ml', variant: 'Standar 50ml', type: 'Eceran', qty: 1, price: 49000, subtotal: 49000, isPreOrder: true }
        ]
      },
      {
        id: 'RK-0010',
        recipient: 'Siti Nurhaliza',
        city: 'Surabaya',
        address: 'Jl. Jemursari Selatan No. 19',
        phone: '087811223344',
        itemCount: 1,
        total: 85000,
        subtotal: 85000,
        shippingFee: 0,
        courier: 'Diantar Penjual',
        service: 'Sameday',
        estimate: 'Selesai',
        trackingNumber: '',
        paymentMethod: 'QRIS',
        paymentStatus: 'Lunas',
        orderStatus: 'Selesai',
        date: '3 Nov 2026',
        paymentDeadline: '3 Nov 2026 15:00',
        paidAt: '3 Nov 2026 14:02',
        items: [
          { product: 'Dewy Cushion SPF 35', variant: '01 Light Sand', type: 'Eceran', qty: 1, price: 85000, subtotal: 85000, isPreOrder: false }
        ]
      }
    ],
    incomingStock: [
      { id: 1, date: '4 Nov 2026', product: 'Glow Velvet Lip Tint - 01 Rose Bloom', wholesaler: 'Grosir Kosmetik Jaya', qty: 24, modalPrice: 28000, recordedBy: 'Ariel' },
      { id: 2, date: '2 Nov 2026', product: 'Dewy Cushion SPF 35 - 01 Light Sand', wholesaler: 'Distributor Surya Ayu', qty: 12, modalPrice: 72000, recordedBy: 'Ariel' },
      { id: 3, date: '28 Okt 2026', product: 'Matte Powder Foundation - Natural', wholesaler: 'Grosir Kosmetik Jaya', qty: 15, modalPrice: 42000, recordedBy: 'Ariel' }
    ],
    directSales: [
      { id: 1, date: '5 Nov 2026', customer: 'Ibu Arini (Tetangga RT 03)', itemCount: 2, total: 76000, note: 'Beli langsung door-to-door tunai' },
      { id: 2, date: '3 Nov 2026', customer: 'Mbak Cindy', itemCount: 1, total: 38000, note: 'Titip pesan via WA bayar cash' }
    ],
    activeProductFormId: null // null for new, number for edit
  };

  // =========================================================================
  // DATA CONTOH GRAFIK PENJUALAN & BARANG TERJUAL (Section 5.1 Revisi)
  // Dipisah dalam variabel sendiri agar mudah diganti data asli
  // =========================================================================
  const sampleSalesMonthlyData = [
    { label: "2023-12", monthText: "Des 23", online: 32000000, langsung: 19000000 },
    { label: "2024-01", monthText: "Jan 24", online: 36000000, langsung: 21000000 },
    { label: "2024-02", monthText: "Feb 24", online: 40000000, langsung: 23000000 },
    { label: "2024-03", monthText: "Mar 24", online: 45000000, langsung: 27000000 },
    { label: "2024-04", monthText: "Apr 24", online: 49000000, langsung: 30000000 },
    { label: "2024-05", monthText: "Mei 24", online: 55000000, langsung: 34000000 },
    { label: "2024-06", monthText: "Jun 24", online: 60000000, langsung: 38000000 },
    { label: "2024-07", monthText: "Jul 24", online: 66000000, langsung: 43000000 },
    { label: "2024-08", monthText: "Agu 24", online: 73000000, langsung: 49000000 },
    { label: "2024-09", monthText: "Sep 24", online: 79000000, langsung: 53000000 },
    { label: "2024-10", monthText: "Okt 24", online: 85000000, langsung: 58000000 },
    { label: "2024-11", monthText: "Nov 24", online: 90000000, langsung: 63000000 }
  ];

  const sampleSalesDailyData = [
    { label: "2024-10-08", dayText: "8 Okt", online: 1800000, langsung: 950000 },
    { label: "2024-10-09", dayText: "9 Okt", online: 2100000, langsung: 1100000 },
    { label: "2024-10-10", dayText: "10 Okt", online: 1950000, langsung: 1050000 },
    { label: "2024-10-11", dayText: "11 Okt", online: 0, langsung: 0 },
    { label: "2024-10-12", dayText: "12 Okt", online: 2400000, langsung: 1300000 },
    { label: "2024-10-13", dayText: "13 Okt", online: 2100000, langsung: 900000 },
    { label: "2024-10-14", dayText: "14 Okt", online: 2600000, langsung: 1450000 },
    { label: "2024-10-15", dayText: "15 Okt", online: 0, langsung: 0 },
    { label: "2024-10-16", dayText: "16 Okt", online: 2200000, langsung: 1150000 },
    { label: "2024-10-17", dayText: "17 Okt", online: 2500000, langsung: 1400000 },
    { label: "2024-10-18", dayText: "18 Okt", online: 2000000, langsung: 950000 },
    { label: "2024-10-19", dayText: "19 Okt", online: 0, langsung: 850000 },
    { label: "2024-10-20", dayText: "20 Okt", online: 2700000, langsung: 1500000 },
    { label: "2024-10-21", dayText: "21 Okt", online: 2150000, langsung: 1100000 },
    { label: "2024-10-22", dayText: "22 Okt", online: 2800000, langsung: 1600000 },
    { label: "2024-10-23", dayText: "23 Okt", online: 0, langsung: 0 },
    { label: "2024-10-24", dayText: "24 Okt", online: 2350000, langsung: 1250000 },
    { label: "2024-10-25", dayText: "25 Okt", online: 2100000, langsung: 1000000 },
    { label: "2024-10-26", dayText: "26 Okt", online: 2900000, langsung: 1700000 },
    { label: "2024-10-27", dayText: "27 Okt", online: 1900000, langsung: 950000 },
    { label: "2024-10-28", dayText: "28 Okt", online: 0, langsung: 0 },
    { label: "2024-10-29", dayText: "29 Okt", online: 2650000, langsung: 1400000 },
    { label: "2024-10-30", dayText: "30 Okt", online: 2500000, langsung: 1350000 },
    { label: "2024-10-31", dayText: "31 Okt", online: 2200000, langsung: 1100000 },
    { label: "2024-11-01", dayText: "1 Nov", online: 3100000, langsung: 1800000 },
    { label: "2024-11-02", dayText: "2 Nov", online: 0, langsung: 0 },
    { label: "2024-11-03", dayText: "3 Nov", online: 2750000, langsung: 1500000 },
    { label: "2024-11-04", dayText: "4 Nov", online: 2600000, langsung: 1300000 },
    { label: "2024-11-05", dayText: "5 Nov", online: 2950000, langsung: 1750000 },
    { label: "2024-11-06", dayText: "6 Nov", online: 3200000, langsung: 1900000 }
  ];

  const sampleProductSalesData = {
    '6m': [
      { nama: "Lip Cream Matte", pcs: 340 },
      { nama: "Bedak Tabur", pcs: 260 },
      { nama: "Serum Vitamin C", pcs: 210 },
      { nama: "Sunscreen SPF 50", pcs: 175 },
      { nama: "Maskara Waterproof", pcs: 130 },
      { nama: "Pelembap Wajah", pcs: 85 },
      { nama: "Cushion", pcs: 60 },
      { nama: "Blush On Cream", pcs: 45 }
    ],
    '30': [
      { nama: "Lip Cream Matte", pcs: 68 },
      { nama: "Bedak Tabur", pcs: 52 },
      { nama: "Serum Vitamin C", pcs: 44 },
      { nama: "Sunscreen SPF 50", pcs: 35 },
      { nama: "Maskara Waterproof", pcs: 26 },
      { nama: "Pelembap Wajah", pcs: 18 },
      { nama: "Cushion", pcs: 12 },
      { nama: "Blush On Cream", pcs: 9 }
    ],
    '12m': [
      { nama: "Lip Cream Matte", pcs: 650 },
      { nama: "Bedak Tabur", pcs: 490 },
      { nama: "Serum Vitamin C", pcs: 390 },
      { nama: "Sunscreen SPF 50", pcs: 320 },
      { nama: "Maskara Waterproof", pcs: 240 },
      { nama: "Pelembap Wajah", pcs: 160 },
      { nama: "Cushion", pcs: 110 },
      { nama: "Blush On Cream", pcs: 80 }
    ]
  };

  // =========================================================================
  // 2. FORMATTER BANTUAN DATA
  // =========================================================================

  function formatRupiah(num) {
    if (isNaN(num)) return 'Rp0';
    return 'Rp' + num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  }

  function formatChartYLabel(val) {
    if (val === 0) return 'Rp 0';
    if (val >= 1000000) {
      const jt = val / 1000000;
      return `Rp ${jt % 1 === 0 ? jt : jt.toFixed(1).replace('.', ',')} Jt`;
    }
    if (val >= 1000) {
      const rb = val / 1000;
      return `Rp ${rb % 1 === 0 ? rb : rb.toFixed(1).replace('.', ',')} Rb`;
    }
    return `Rp ${val}`;
  }

  function getSmoothCurvePath(points) {
    if (points.length === 0) return '';
    if (points.length === 1) return `M ${points[0].x} ${points[0].y}`;

    let d = `M ${points[0].x.toFixed(2)} ${points[0].y.toFixed(2)}`;
    for (let i = 0; i < points.length - 1; i++) {
      const p0 = points[i === 0 ? i : i - 1];
      const p1 = points[i];
      const p2 = points[i + 1];
      const p3 = points[i + 2 < points.length ? i + 2 : i + 1];

      const cp1x = p1.x + (p2.x - p0.x) / 6;
      const cp1y = p1.y + (p2.y - p0.y) / 6;

      const cp2x = p2.x - (p3.x - p1.x) / 6;
      const cp2y = p2.y - (p3.y - p1.y) / 6;

      d += ` C ${cp1x.toFixed(2)} ${cp1y.toFixed(2)}, ${cp2x.toFixed(2)} ${cp2y.toFixed(2)}, ${p2.x.toFixed(2)} ${p2.y.toFixed(2)}`;
    }
    return d;
  }

  function getSmoothAreaPath(points, bottomY) {
    if (points.length === 0) return '';
    const curve = getSmoothCurvePath(points);
    const lastX = points[points.length - 1].x;
    const firstX = points[0].x;
    return `${curve} L ${lastX.toFixed(2)} ${bottomY.toFixed(2)} L ${firstX.toFixed(2)} ${bottomY.toFixed(2)} Z`;
  }

  function getStockLabel(product) {
    const totalStock = product.variants.reduce((acc, v) => acc + (v.stock || 0), 0);
    if (totalStock > 0) {
      return { text: 'Tersedia', class: 'status-tersedia' };
    }
    if (product.canPreOrder) {
      return { text: 'Pre-Order', class: 'status-pre-order' };
    }
    return { text: 'Stok Habis', class: 'status-stok-habis' };
  }

  function getOrderStatusClass(status) {
    switch (status) {
      case 'Menunggu Pembayaran': return 'status-menunggu-pembayaran';
      case 'Dibayar':             return 'status-dibayar';
      case 'Diproses':            return 'status-diproses';
      case 'Dikirim':             return 'status-dikirim';
      case 'Selesai':             return 'status-selesai';
      case 'Dibatalkan':          return 'status-dibatalkan';
      default:                    return 'status-dibatalkan';
    }
  }

  function getPaymentStatusClass(status) {
    switch (status) {
      case 'Menunggu':    return 'status-menunggu-pembayaran';
      case 'Lunas':       return 'status-lunas';
      case 'Gagal':       return 'status-gagal';
      case 'Kedaluwarsa': return 'status-kedaluwarsa';
      default:            return 'status-dibatalkan';
    }
  }

  function getGreeting() {
    const hour = new Date().getHours();
    if (hour >= 4 && hour < 11) return 'Selamat pagi';
    if (hour >= 11 && hour < 15) return 'Selamat siang';
    if (hour >= 15 && hour < 18) return 'Selamat sore';
    return 'Selamat malam';
  }

  // =========================================================================
  // 2.1 RENDER GRAFIK PENJUALAN (DUA GARIS HALUS - Section 5.1 Revisi)
  // =========================================================================

  function renderPenjualan(periode = '6m') {
    const wrap = document.getElementById('chartSvgWrap');
    const totalEl = document.getElementById('chartTotalAmount');
    const periodDescEl = document.getElementById('chartPeriodDesc');
    const tooltip = document.getElementById('chartTooltip');

    if (!wrap) return;

    // Ambil data sesuai periode
    let data = [];
    let descText = '6 bulan terakhir';
    if (periode === '6m') {
      data = sampleSalesMonthlyData.slice(-6);
      descText = '6 bulan terakhir';
    } else if (periode === '12m') {
      data = sampleSalesMonthlyData;
      descText = '12 bulan terakhir';
    } else if (periode === '30') {
      data = sampleSalesDailyData;
      descText = '30 hari terakhir';
    }

    const totalOnline = data.reduce((acc, d) => acc + (d.online || 0), 0);
    const totalDirect = data.reduce((acc, d) => acc + (d.langsung || 0), 0);
    const grandTotal = totalOnline + totalDirect;

    if (totalEl) totalEl.textContent = formatRupiah(grandTotal);
    if (periodDescEl) periodDescEl.textContent = descText;

    if (!data || data.length === 0 || grandTotal === 0) {
      wrap.innerHTML = `
        <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: var(--admin-text-muted); font-size: 13px;">
          Belum ada penjualan pada periode ini.
        </div>
      `;
      return;
    }

    const containerW = wrap.clientWidth || 520;
    const isMobile = window.innerWidth < 768;
    const containerH = isMobile ? 180 : 220;

    const padLeft = 68;
    const padRight = 18;
    const padTop = 18;
    const padBottom = 26;

    const plotW = Math.max(100, containerW - padLeft - padRight);
    const plotH = Math.max(80, containerH - padTop - padBottom);
    const bottomY = padTop + plotH;

    // Nilai maksimum sumbu Y
    const maxValRaw = Math.max(...data.map(d => Math.max(d.online || 0, d.langsung || 0)));
    let step = 30000000;
    if (periode === '30') {
      step = 1000000;
    }
    const yMax = Math.max(step * 3, Math.ceil(maxValRaw / (step * 3)) * (step * 3));

    // 4 Garis Grid Horizontal Putus-putus #F0DEDB dengan label rata kiri
    let gridSvg = '';
    const gridCount = 4;
    for (let i = 0; i < gridCount; i++) {
      const val = (yMax / (gridCount - 1)) * (gridCount - 1 - i);
      const yPos = padTop + (plotH / (gridCount - 1)) * i;
      const labelText = formatChartYLabel(val);

      gridSvg += `
        <line x1="${padLeft}" y1="${yPos.toFixed(2)}" x2="${(padLeft + plotW).toFixed(2)}" y2="${yPos.toFixed(2)}" stroke="#F0DEDB" stroke-dasharray="4 4" stroke-width="1" />
        <text x="${padLeft - 10}" y="${(yPos + 4).toFixed(2)}" text-anchor="end" fill="#8A6F76" font-size="11" font-family="var(--admin-font)">${labelText}</text>
      `;
    }

    // Hitung titik koordinat garis
    const n = data.length;
    const pointsOnline = [];
    const pointsDirect = [];

    data.forEach((d, i) => {
      const x = padLeft + (n > 1 ? (i / (n - 1)) * plotW : plotW / 2);
      const yOnline = padTop + plotH - ((d.online || 0) / yMax) * plotH;
      const yDirect = padTop + plotH - ((d.langsung || 0) / yMax) * plotH;

      pointsOnline.push({ x, y: yOnline, data: d, index: i });
      pointsDirect.push({ x, y: yDirect, data: d, index: i });
    });

    const curveDirect = getSmoothCurvePath(pointsDirect);
    const areaDirect = getSmoothAreaPath(pointsDirect, bottomY);

    const curveOnline = getSmoothCurvePath(pointsOnline);
    const areaOnline = getSmoothAreaPath(pointsOnline, bottomY);

    // Titik terakhir Online: penanda bulat terisi radius 4 px, border putih 2 px
    const lastPOnline = pointsOnline[pointsOnline.length - 1];

    // Label sumbu X
    let xLabelsSvg = '';
    data.forEach((d, i) => {
      let showX = false;
      let xText = d.monthText || d.dayText || d.label;

      if (periode === '6m') {
        showX = true;
      } else if (periode === '12m') {
        showX = (i % 2 === 0 || i === n - 1);
      } else if (periode === '30') {
        showX = (i % 5 === 0 || i === n - 1);
      }

      if (showX) {
        const xPos = pointsOnline[i].x;
        xLabelsSvg += `
          <text x="${xPos.toFixed(2)}" y="${(bottomY + 18).toFixed(2)}" text-anchor="middle" fill="#8A6F76" font-size="11" font-family="var(--admin-font)">${xText}</text>
        `;
      }
    });

    // Hit columns untuk hover dan fokus Tab
    let hitsSvg = '';
    const hitW = plotW / n;
    data.forEach((d, i) => {
      const pOn = pointsOnline[i];
      const pDir = pointsDirect[i];
      const timeText = d.monthText || d.dayText || d.label;
      const ariaLabel = `${timeText}: online ${formatRupiah(d.online)}, langsung ${formatRupiah(d.langsung)}, total ${formatRupiah(d.online + d.langsung)}`;

      hitsSvg += `
        <rect class="chart-point-hit"
              data-idx="${i}"
              x="${(pOn.x - hitW / 2).toFixed(2)}"
              y="${padTop}"
              width="${hitW.toFixed(2)}"
              height="${plotH}"
              fill="transparent"
              tabindex="0"
              role="graphics-symbol"
              aria-label="${ariaLabel}" />
      `;
    });

    wrap.innerHTML = `
      <svg style="width: 100%; height: 100%; overflow: visible;" viewBox="0 0 ${containerW} ${containerH}">
        <!-- Grid horizontal -->
        ${gridSvg}

        <!-- Garis kursor vertikal hover (dinamis) -->
        <line id="hoverLineCursor" x1="0" y1="${padTop}" x2="0" y2="${bottomY}" stroke="#F0DEDB" stroke-dasharray="3 3" stroke-width="1" style="display: none;" />

        <!-- Area di bawah garis: solid dengan opacity 14% (tanpa gradien) -->
        <path d="${areaDirect}" fill="#EBA9B8" fill-opacity="0.14" stroke="none" />
        <path d="${areaOnline}" fill="#9C4A63" fill-opacity="0.14" stroke="none" />

        <!-- Garis kurva halus tebal 2.5px -->
        <path d="${curveDirect}" fill="none" stroke="#EBA9B8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
        <path d="${curveOnline}" fill="none" stroke="#9C4A63" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />

        <!-- Penanda bulat permanen pada titik terakhir Online -->
        <circle cx="${lastPOnline.x.toFixed(2)}" cy="${lastPOnline.y.toFixed(2)}" r="4" fill="#9C4A63" stroke="#FFFFFF" stroke-width="2" />

        <!-- Marker dinamis saat hover/fokus -->
        <circle id="hoverMarkerDirect" cx="0" cy="0" r="4" fill="#EBA9B8" stroke="#FFFFFF" stroke-width="2" style="display: none;" />
        <circle id="hoverMarkerOnline" cx="0" cy="0" r="4" fill="#9C4A63" stroke="#FFFFFF" stroke-width="2" style="display: none;" />

        <!-- Label sumbu X -->
        ${xLabelsSvg}

        <!-- Area interaksi Tab & Hover -->
        ${hitsSvg}
      </svg>
    `;

    // Event Hover & Fokus pada titik
    const hoverCursor = wrap.querySelector('#hoverLineCursor');
    const hoverMarkerDir = wrap.querySelector('#hoverMarkerDirect');
    const hoverMarkerOn = wrap.querySelector('#hoverMarkerOnline');

    wrap.querySelectorAll('.chart-point-hit').forEach(hitEl => {
      const i = parseInt(hitEl.dataset.idx);
      const pOn = pointsOnline[i];
      const pDir = pointsDirect[i];
      const d = data[i];

      const showPointTooltip = () => {
        const timeText = d.monthText || d.dayText || d.label;
        const tot = (d.online || 0) + (d.langsung || 0);

        tooltip.innerHTML = `
          <div style="font-weight: 600; color: #4A2C35; margin-bottom: 4px;">${timeText}</div>
          <div style="display: flex; align-items: center; gap: 6px; color: #8A6F76;">
            <span style="width: 8px; height: 8px; border-radius: 50%; background-color: #9C4A63; display: inline-block;"></span>
            Online: <strong style="color: #4A2C35;">${formatRupiah(d.online)}</strong>
          </div>
          <div style="display: flex; align-items: center; gap: 6px; color: #8A6F76; margin-top: 2px;">
            <span style="width: 8px; height: 8px; border-radius: 50%; background-color: #EBA9B8; display: inline-block;"></span>
            Langsung: <strong style="color: #4A2C35;">${formatRupiah(d.langsung)}</strong>
          </div>
          <div style="margin-top: 4px; padding-top: 4px; border-top: 1px solid #F0DEDB; font-weight: 600; color: #9C4A63;">
            Total: ${formatRupiah(tot)}
          </div>
        `;

        tooltip.style.left = `${pOn.x}px`;
        tooltip.style.top = `${Math.min(pOn.y, pDir.y) - 6}px`;
        tooltip.style.display = 'block';

        if (hoverCursor) {
          hoverCursor.setAttribute('x1', pOn.x);
          hoverCursor.setAttribute('x2', pOn.x);
          hoverCursor.style.display = 'block';
        }
        if (hoverMarkerOn) {
          hoverMarkerOn.setAttribute('cx', pOn.x);
          hoverMarkerOn.setAttribute('cy', pOn.y);
          hoverMarkerOn.style.display = 'block';
        }
        if (hoverMarkerDir) {
          hoverMarkerDir.setAttribute('cx', pDir.x);
          hoverMarkerDir.setAttribute('cy', pDir.y);
          hoverMarkerDir.style.display = 'block';
        }
      };

      const hidePointTooltip = () => {
        tooltip.style.display = 'none';
        if (hoverCursor) hoverCursor.style.display = 'none';
        if (hoverMarkerOn) hoverMarkerOn.style.display = 'none';
        if (hoverMarkerDir) hoverMarkerDir.style.display = 'none';
      };

      hitEl.addEventListener('mouseenter', showPointTooltip);
      hitEl.addEventListener('mouseleave', hidePointTooltip);
      hitEl.addEventListener('focus', showPointTooltip);
      hitEl.addEventListener('blur', hidePointTooltip);
    });
  }

  // =========================================================================
  // 2.2 RENDER BARANG TERJUAL (PIE CHART - Section 5.1 Revisi)
  // =========================================================================

  function renderBarangTerjual(periode = '6m') {
    const wrap = document.getElementById('pieSvgWrap');
    const legendWrap = document.getElementById('pieLegendWrap');
    const tooltip = document.getElementById('pieTooltip');
    const descEl = document.getElementById('piePeriodDesc');

    if (!wrap || !legendWrap) return;

    let descText = '6 bulan terakhir';
    if (periode === '6m') descText = '6 bulan terakhir';
    else if (periode === '12m') descText = '12 bulan terakhir';
    else if (periode === '30') descText = '30 hari terakhir';

    if (descEl) descEl.textContent = descText;

    const rawList = sampleProductSalesData[periode] || sampleProductSalesData['6m'];
    const sorted = [...rawList].sort((a, b) => b.pcs - a.pcs);

    const top5 = sorted.slice(0, 5);
    const remainderPcs = sorted.slice(5).reduce((acc, p) => acc + p.pcs, 0);

    const slices = [...top5];
    if (remainderPcs > 0) {
      slices.push({ nama: 'Lainnya', pcs: remainderPcs });
    }

    const totalPcs = slices.reduce((acc, s) => acc + s.pcs, 0);

    if (totalPcs === 0) {
      wrap.innerHTML = `
        <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: var(--admin-text-muted); font-size: 13px;">
          Belum ada barang terjual pada periode ini.
        </div>
      `;
      legendWrap.innerHTML = '';
      return;
    }

    const pieColors = ['#9C4A63', '#EBA9B8', '#F6D6A8', '#C77D92', '#4A2C35', '#D9C9CC'];

    const cx = 80;
    const cy = 80;
    const r = 70; // diameter 140-160px

    let currentAngle = -Math.PI / 2; // Mulai dari jam 12
    let pathsSvg = '';

    slices.forEach((item, idx) => {
      const frac = item.pcs / totalPcs;
      const angleSpan = frac * 2 * Math.PI;
      const startAngle = currentAngle;
      const endAngle = currentAngle + angleSpan;
      currentAngle = endAngle;

      const x1 = cx + r * Math.cos(startAngle);
      const y1 = cy + r * Math.sin(startAngle);
      const x2 = cx + r * Math.cos(endAngle);
      const y2 = cy + r * Math.sin(endAngle);

      const largeArc = angleSpan > Math.PI ? 1 : 0;
      const d = `M ${cx} ${cy} L ${x1.toFixed(2)} ${y1.toFixed(2)} A ${r} ${r} 0 ${largeArc} 1 ${x2.toFixed(2)} ${y2.toFixed(2)} Z`;

      const midAngle = (startAngle + endAngle) / 2;
      const dx = (Math.cos(midAngle) * 4).toFixed(2);
      const dy = (Math.sin(midAngle) * 4).toFixed(2);
      const color = pieColors[idx % pieColors.length];
      const percent = Math.round(frac * 100);

      pathsSvg += `
        <path class="pie-slice"
              data-idx="${idx}"
              data-dx="${dx}"
              data-dy="${dy}"
              d="${d}"
              fill="${color}"
              stroke="#FFFFFF"
              stroke-width="2"
              tabindex="0"
              role="graphics-symbol"
              aria-label="${item.nama}: ${item.pcs} pcs (${percent}%)" />
      `;
    });

    wrap.innerHTML = `
      <svg style="width: 100%; height: 100%; overflow: visible;" viewBox="0 0 160 160">
        ${pathsSvg}
      </svg>
    `;

    // Legenda Baris di Bawah Pie
    legendWrap.innerHTML = `
      <div style="display: flex; flex-direction: column; gap: 6px; margin-top: 14px; width: 100%;">
        ${slices.map((item, idx) => {
          const color = pieColors[idx % pieColors.length];
          const percent = Math.round((item.pcs / totalPcs) * 100);
          return `
            <div class="pie-legend-item">
              <span style="width: 10px; height: 10px; border-radius: 2px; background-color: ${color}; flex-shrink: 0;"></span>
              <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 130px; font-size: 12px; color: var(--admin-text);" title="${item.nama}">${item.nama}</span>
              <span style="margin-left: auto; font-size: 12px; color: var(--admin-text-muted); font-weight: 500; white-space: nowrap;">${item.pcs} pcs, ${percent}%</span>
            </div>
          `;
        }).join('')}
      </div>
    `;

    // Event Hover & Fokus Irisan Pie
    wrap.querySelectorAll('.pie-slice').forEach(sliceEl => {
      const idx = parseInt(sliceEl.dataset.idx);
      const item = slices[idx];
      const dx = sliceEl.dataset.dx;
      const dy = sliceEl.dataset.dy;
      const percent = Math.round((item.pcs / totalPcs) * 100);

      const showSliceTooltip = () => {
        sliceEl.style.transform = `translate(${dx}px, ${dy}px)`;

        tooltip.innerHTML = `
          <div style="font-weight: 600; color: #4A2C35;">${item.nama}</div>
          <div style="color: #8A6F76; margin-top: 2px;">${item.pcs} pcs (${percent}%)</div>
        `;
        tooltip.style.left = `${cx + parseFloat(dx)}px`;
        tooltip.style.top = `${cy + parseFloat(dy) - 10}px`;
        tooltip.style.display = 'block';
      };

      const hideSliceTooltip = () => {
        sliceEl.style.transform = 'translate(0, 0)';
        tooltip.style.display = 'none';
      };

      sliceEl.addEventListener('mouseenter', showSliceTooltip);
      sliceEl.addEventListener('mouseleave', hideSliceTooltip);
      sliceEl.addEventListener('focus', showSliceTooltip);
      sliceEl.addEventListener('blur', hideSliceTooltip);
    });
  }

  // =========================================================================
  // 3. TOAST NOTIFICATION SYSTEM
  // =========================================================================

  function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.setAttribute('role', 'alert');

    let iconSymbol = 'icon-check';
    if (type === 'danger') iconSymbol = 'icon-alert-triangle';
    if (type === 'info') iconSymbol = 'icon-alert-circle';

    toast.innerHTML = `
      <svg class="icon icon-md"><use href="#${iconSymbol}"></use></svg>
      <div class="toast-body">${message}</div>
      <button class="toast-close" aria-label="Tutup notifikasi">
        <svg class="icon icon-sm"><use href="#icon-x"></use></svg>
      </button>
    `;

    container.appendChild(toast);

    // Animasi masuk
    requestAnimationFrame(() => {
      toast.classList.add('show');
    });

    const closeBtn = toast.querySelector('.toast-close');
    const dismiss = () => {
      toast.classList.remove('show');
      setTimeout(() => toast.remove(), 200);
    };

    closeBtn.addEventListener('click', dismiss);
    setTimeout(dismiss, 4000);
  }

  // =========================================================================
  // 4. DIALOG & MODAL BANTUAN
  // =========================================================================

  function showConfirmDialog({ iconClass, iconSymbol, title, descHtml, confirmText, confirmClass, onConfirm }) {
    const dialog = document.getElementById('adminConfirmDialog');
    if (!dialog) return;

    const iconWrap = dialog.querySelector('.modal-icon-wrap');
    iconWrap.className = `modal-icon-wrap ${iconClass}`;
    iconWrap.innerHTML = `<svg class="icon icon-lg"><use href="#${iconSymbol}"></use></svg>`;

    dialog.querySelector('.modal-title').textContent = title;
    dialog.querySelector('.modal-desc').innerHTML = descHtml;

    const confirmBtn = dialog.querySelector('#btnDialogConfirm');
    confirmBtn.className = `btn btn-sm ${confirmClass}`;
    confirmBtn.textContent = confirmText;

    const cancelBtn = dialog.querySelector('#btnDialogCancel');

    const handleConfirm = () => {
      dialog.close();
      cleanup();
      if (typeof onConfirm === 'function') onConfirm();
    };

    const handleCancel = () => {
      dialog.close();
      cleanup();
    };

    function cleanup() {
      confirmBtn.removeEventListener('click', handleConfirm);
      cancelBtn.removeEventListener('click', handleCancel);
    }

    confirmBtn.addEventListener('click', handleConfirm);
    cancelBtn.addEventListener('click', handleCancel);

    dialog.showModal();
  }

  // =========================================================================
  // 5. SISTEM FLYOUT DETAIL (RAIL >= 768px)
  // =========================================================================

  const flyoutData = {
    ringkasan: {
      title: 'Ringkasan',
      desc: 'Ringkasan toko hari ini.',
      icon: 'icon-layout-dashboard',
      singlePage: true,
      route: 'ringkasan'
    },
    katalog: {
      title: 'Katalog',
      desc: 'Produk, kategori, dan ulasan',
      icon: 'icon-package',
      subpages: [
        { route: 'produk', name: 'Produk', desc: 'Daftar produk, harga, varian, dan foto.', icon: 'icon-package' },
        { route: 'kategori', name: 'Kategori', desc: 'Pengelompokan produk.', icon: 'icon-tags' },
        { route: 'ulasan', name: 'Ulasan', desc: 'Tinjau dan sembunyikan ulasan.', icon: 'icon-star' }
      ]
    },
    pesanan: {
      title: 'Pesanan',
      desc: 'Daftar pesanan, status, dan nomor resi.',
      icon: 'icon-shopping-bag',
      singlePage: true,
      route: 'pesanan',
      infoNote: '4 pesanan perlu diproses'
    },
    stokKeuangan: {
      title: 'Stok dan Keuangan',
      desc: 'Barang masuk, penjualan langsung, dan rekap',
      icon: 'icon-wallet',
      subpages: [
        { route: 'barang-masuk', name: 'Barang Masuk', desc: 'Catat stok masuk dari grosir.', icon: 'icon-package-plus', badge: '3' },
        { route: 'penjualan-langsung', name: 'Penjualan Langsung', desc: 'Catat penjualan door-to-door.', icon: 'icon-hand-coins' },
        { route: 'rekap-bulanan', name: 'Rekap Bulanan', desc: 'Penjualan, laba, dan produk terlaris.', icon: 'icon-bar-chart-3' }
      ]
    },
    pengaturan: {
      title: 'Pengaturan Toko',
      desc: 'Info toko, WhatsApp, dan aturan stok.',
      icon: 'icon-settings',
      singlePage: true,
      route: 'pengaturan'
    },
    profil: {
      title: 'Admin',
      desc: 'Pemilik Toko RiasKu',
      icon: 'icon-user',
      isProfile: true
    }
  };

  let flyoutOpenTimeout = null;
  let flyoutCloseTimeout = null;
  let currentFlyoutKey = null;

  function initFlyoutSystem() {
    const railItems = document.querySelectorAll('.rail-item, .rail-avatar');
    const flyoutContainer = document.getElementById('adminFlyout');
    if (!flyoutContainer) return;

    railItems.forEach(item => {
      const groupKey = item.dataset.group;

      // Mouse Enter pada ikon rail
      item.addEventListener('mouseenter', () => {
        clearTimeout(flyoutCloseTimeout);

        if (currentFlyoutKey && currentFlyoutKey !== groupKey) {
          // Berganti antar item tanpa jeda
          openFlyout(groupKey, item);
        } else {
          // Masuk pertama kali dengan jeda 80 ms
          clearTimeout(flyoutOpenTimeout);
          flyoutOpenTimeout = setTimeout(() => {
            openFlyout(groupKey, item);
          }, 80);
        }
      });

      // Mouse Leave dari ikon rail
      item.addEventListener('mouseleave', () => {
        clearTimeout(flyoutOpenTimeout);
        flyoutCloseTimeout = setTimeout(() => {
          closeFlyout();
        }, 150);
      });

      // Fokus Keyboard (Tab)
      item.addEventListener('focusin', () => {
        openFlyout(groupKey, item);
      });

      // Klik Ikon Rail
      item.addEventListener('click', (e) => {
        const group = flyoutData[groupKey];
        if (!group) return;

        if (group.singlePage) {
          navigateTo(group.route);
          closeFlyout();
        } else if (group.subpages && group.subpages.length > 0) {
          // Menuju sub-halaman pertama
          navigateTo(group.subpages[0].route);
          closeFlyout();
        }
      });
    });

    // Mouse pada Flyout agar tidak menutup saat kursor pindah ke dalamnya
    flyoutContainer.addEventListener('mouseenter', () => {
      clearTimeout(flyoutCloseTimeout);
    });

    flyoutContainer.addEventListener('mouseleave', () => {
      flyoutCloseTimeout = setTimeout(() => {
        closeFlyout();
      }, 150);
    });

    // Escape untuk menutup flyout
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        closeFlyout();
      }
    });

    // Ketuk di luar untuk layar sentuh
    document.addEventListener('click', (e) => {
      if (!e.target.closest('.admin-rail') && !e.target.closest('.admin-flyout-container')) {
        closeFlyout();
      }
    });
  }

  function openFlyout(groupKey, triggerEl) {
    const group = flyoutData[groupKey];
    if (!group) return;

    const flyout = document.getElementById('adminFlyout');
    if (!flyout) return;

    currentFlyoutKey = groupKey;

    // Render Konten Flyout
    let html = `
      <div class="flyout-card" role="menu">
        <div class="flyout-header">
          <div class="flyout-header-icon">
            <svg class="icon icon-md"><use href="#${group.icon}"></use></svg>
          </div>
          <div class="flyout-header-text">
            <div class="flyout-header-title">${group.title}</div>
            <div class="flyout-header-desc">${group.desc}</div>
          </div>
        </div>
        <div class="flyout-divider"></div>
    `;

    if (group.subpages) {
      html += `<ul class="flyout-list">`;
      group.subpages.forEach(sub => {
        const isActive = isRouteActive(sub.route);
        html += `
          <li>
            <a href="#${sub.route}" class="flyout-item ${isActive ? 'active' : ''}" role="menuitem" data-route="${sub.route}">
              <svg class="icon icon-md flyout-item-icon"><use href="#${sub.icon}"></use></svg>
              <div class="flyout-item-body">
                <div class="flyout-item-title-row">
                  <span class="flyout-item-title">${sub.name}</span>
                  ${sub.badge ? `<span class="flyout-item-badge">${sub.badge}</span>` : ''}
                </div>
                <div class="flyout-item-desc">${sub.desc}</div>
              </div>
            </a>
          </li>
        `;
      });
      html += `</ul>`;
    } else if (group.singlePage) {
      if (group.infoNote) {
        html += `<div class="flyout-info-box">${group.infoNote}</div>`;
      }
      html += `
        <a href="#${group.route}" class="btn btn-sm btn-primary" style="margin-top: 8px; justify-content: center;" data-route="${group.route}">
          Buka ${group.title}
        </a>
      `;
    } else if (group.isProfile) {
      html += `
        <div style="display: flex; flex-direction: column; gap: 8px; margin-top: 4px;">
          <a href="login.html" target="_blank" class="btn btn-sm btn-secondary" style="justify-content: center;">
            <svg class="icon icon-sm"><use href="#icon-external-link"></use></svg>
            Lihat Toko
          </a>
          <a href="login.html" class="btn btn-sm btn-ghost" style="justify-content: center; color: var(--admin-danger);">
            <svg class="icon icon-sm"><use href="#icon-log-out"></use></svg>
            Keluar
          </a>
        </div>
      `;
    }

    html += `</div>`;
    flyout.innerHTML = html;

    // Pasang Event Klik pada item flyout
    flyout.querySelectorAll('[data-route]').forEach(link => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        const route = link.dataset.route;
        navigateTo(route);
        closeFlyout();
      });
    });

    // Posisikan Flyout Sejajar dengan Atas Elemen
    const rect = triggerEl.getBoundingClientRect();
    let top = rect.top;

    flyout.classList.add('is-visible');
    triggerEl.setAttribute('aria-expanded', 'true');

    // Hindari kartu keluar dari bagian bawah viewport
    const flyoutHeight = flyout.offsetHeight || 200;
    if (group.isProfile) {
      // Flyout profil membuka ke atas
      top = rect.bottom - flyoutHeight;
    } else if (top + flyoutHeight > window.innerHeight - 16) {
      top = Math.max(16, window.innerHeight - flyoutHeight - 16);
    }

    flyout.style.top = `${top}px`;
  }

  function closeFlyout() {
    const flyout = document.getElementById('adminFlyout');
    if (!flyout) return;

    flyout.classList.remove('is-visible');
    currentFlyoutKey = null;

    document.querySelectorAll('.rail-item, .rail-avatar').forEach(item => {
      item.setAttribute('aria-expanded', 'false');
    });
  }

  function isRouteActive(route) {
    if (state.currentRoute === route) return true;
    if (route === 'produk' && (state.currentRoute === 'produk-form' || state.currentRoute === 'produk-tambah')) return true;
    if (route === 'pesanan' && state.currentRoute === 'pesanan-detail') return true;
    return false;
  }

  // =========================================================================
  // 6. NAVIGASI MOBILE DRAWER (< 768px)
  // =========================================================================

  function initMobileDrawer() {
    const hamburger = document.getElementById('btnHamburger');
    const overlay = document.getElementById('mobileOverlay');
    const drawer = document.getElementById('mobileDrawer');
    const closeBtn = document.getElementById('btnDrawerClose');

    if (!hamburger || !drawer || !overlay) return;

    const openDrawer = () => {
      overlay.classList.add('is-open');
      drawer.classList.add('is-open');
      document.body.style.overflow = 'hidden';
    };

    const closeDrawer = () => {
      overlay.classList.remove('is-open');
      drawer.classList.remove('is-open');
      document.body.style.overflow = '';
    };

    hamburger.addEventListener('click', openDrawer);
    if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
    overlay.addEventListener('click', closeDrawer);

    // Akordeon di dalam drawer
    drawer.querySelectorAll('.drawer-accordion-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const accordion = btn.closest('.drawer-accordion');
        if (accordion) accordion.classList.toggle('is-open');
      });
    });

    // Navigasi tautan dalam drawer
    drawer.querySelectorAll('[data-route]').forEach(link => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        const route = link.dataset.route;
        navigateTo(route);
        closeDrawer();
      });
    });
  }

  // =========================================================================
  // 7. ROUTING & RENDERING VIEW HALAMAN
  // =========================================================================

  function navigateTo(route) {
    window.location.hash = route;
  }

  function handleHashChange() {
    let hash = window.location.hash.replace('#', '') || 'ringkasan';

    // Sub-route handling (contoh: pesanan-detail)
    state.currentRoute = hash;
    updateNavigationStates();
    renderCurrentPage();
    window.scrollTo(0, 0);
  }

  function updateNavigationStates() {
    // 1. Update Rail active state
    document.querySelectorAll('.rail-item').forEach(item => {
      const group = item.dataset.group;
      let active = false;

      if (group === 'ringkasan' && state.currentRoute === 'ringkasan') active = true;
      if (group === 'katalog' && ['produk', 'produk-form', 'produk-tambah', 'kategori', 'ulasan'].includes(state.currentRoute)) active = true;
      if (group === 'pesanan' && ['pesanan', 'pesanan-detail'].includes(state.currentRoute)) active = true;
      if (group === 'stokKeuangan' && ['barang-masuk', 'penjualan-langsung', 'rekap-bulanan'].includes(state.currentRoute)) active = true;
      if (group === 'pengaturan' && state.currentRoute === 'pengaturan') active = true;

      item.classList.toggle('active', active);
    });

    // 2. Update Drawer links
    document.querySelectorAll('.drawer-link, .drawer-sublink').forEach(link => {
      const route = link.dataset.route;
      link.classList.toggle('active', isRouteActive(route));
    });
  }

  function renderCurrentPage() {
    const pageHeader = document.getElementById('pageHeader');
    const pageContent = document.getElementById('pageContent');
    if (!pageHeader || !pageContent) return;

    switch (state.currentRoute) {
      case 'ringkasan':
        renderRingkasan(pageHeader, pageContent);
        break;
      case 'produk':
        renderProdukList(pageHeader, pageContent);
        break;
      case 'produk-tambah':
      case 'produk-form':
        renderProdukForm(pageHeader, pageContent);
        break;
      case 'kategori':
        renderKategori(pageHeader, pageContent);
        break;
      case 'ulasan':
        renderUlasan(pageHeader, pageContent);
        break;
      case 'pesanan':
        renderPesananList(pageHeader, pageContent);
        break;
      case 'pesanan-detail':
        renderPesananDetail(pageHeader, pageContent);
        break;
      case 'barang-masuk':
        renderBarangMasuk(pageHeader, pageContent);
        break;
      case 'penjualan-langsung':
        renderPenjualanLangsung(pageHeader, pageContent);
        break;
      case 'rekap-bulanan':
        renderRekapBulanan(pageHeader, pageContent);
        break;
      case 'pengaturan':
        renderPengaturan(pageHeader, pageContent);
        break;
      default:
        renderRingkasan(pageHeader, pageContent);
    }
  }

  // =========================================================================
  // 8. IMPLEMENTASI VIEW HALAMAN LENGKAP
  // =========================================================================

  // -------------------------------------------------------------------------
  // 8.1 Ringkasan (Dashboard)
  // -------------------------------------------------------------------------
  function renderRingkasan(header, content) {
    header.innerHTML = `
      <div class="admin-header-row-1">
        <button class="admin-hamburger" id="btnHamburger" aria-label="Buka menu navigasi">
          <svg class="icon icon-md"><use href="#icon-menu"></use></svg>
        </button>
        <div class="admin-breadcrumb">
          <span>Admin</span>
          <span class="admin-breadcrumb-sep">></span>
          <span>Ringkasan</span>
        </div>
      </div>
      <div class="admin-header-row-2">
        <div class="admin-title-area">
          <h1 class="admin-title">Selamat datang kembali, Admin</h1>
          <div class="admin-subtitle">Semoga harimu menyenangkan.</div>
        </div>
        <div class="admin-actions-area">
          <a href="login.html" target="_blank" class="btn btn-secondary">
            <svg class="icon icon-sm"><use href="#icon-external-link"></use></svg>
            Lihat Toko
          </a>
        </div>
      </div>
    `;

    // Metrik
    const pendingOrdersCount = state.orders.filter(o => o.orderStatus === 'Dibayar' || o.orderStatus === 'Diproses').length;
    const unpaidOrdersCount = state.orders.filter(o => o.orderStatus === 'Menunggu Pembayaran').length;
    const restockVariantsCount = 3; // Dari data varian stok rendah
    const monthlySalesTotal = 1250000;

    content.innerHTML = `
      <!-- 4 Kartu Metrik -->
      <div class="metric-grid-4">
        <div class="metric-card" onclick="window.location.hash='pesanan'">
          <div class="metric-top">
            <span class="metric-label">Perlu ditindak</span>
            ${pendingOrdersCount > 0 ? '<span class="status-pill status-menunggu-pembayaran">Perlu ditindak</span>' : ''}
          </div>
          <div class="metric-value">${pendingOrdersCount}</div>
          <div class="metric-help">Pesanan siap diproses & dikirim</div>
        </div>

        <div class="metric-card" onclick="window.location.hash='pesanan'">
          <div class="metric-top">
            <span class="metric-label">Menunggu pembayaran</span>
          </div>
          <div class="metric-value">${unpaidOrdersCount}</div>
          <div class="metric-help">Pesanan belum dilunasi</div>
        </div>

        <div class="metric-card" onclick="window.location.hash='barang-masuk'">
          <div class="metric-top">
            <span class="metric-label">Segera restock</span>
            <span class="status-pill status-segera-restock">${restockVariantsCount} varian</span>
          </div>
          <div class="metric-value">${restockVariantsCount}</div>
          <div class="metric-help">Di bawah batas stok minimum</div>
        </div>

        <div class="metric-card" onclick="window.location.hash='rekap-bulanan'">
          <div class="metric-top">
            <span class="metric-label">Penjualan bulan ini</span>
          </div>
          <div class="metric-value is-currency">${formatRupiah(monthlySalesTotal)}</div>
          <div class="metric-help">Nov 2026 (Online & Langsung)</div>
        </div>
      </div>

      <!-- Baris Grafik Penjualan & Barang Terjual (Revisi Section 5.1) -->
      <div class="ringkasan-charts-row">
        <!-- Kartu Kiri: Grafik Penjualan -->
        <div class="admin-card chart-card-sales">
          <div class="admin-card-header" style="align-items: flex-start;">
            <div>
              <div class="admin-card-title">Grafik Penjualan</div>
              <div id="chartTotalAmount" style="font-size: 24px; font-weight: 700; color: var(--admin-accent); margin: 4px 0 2px;">
                Rp0
              </div>
              <div class="admin-card-desc" id="chartPeriodDesc">6 bulan terakhir</div>
            </div>

            <div class="admin-card-controls">
              <select id="salesPeriodSelect" class="form-select" style="width: auto; height: 36px; padding: 0 14px; border-radius: var(--admin-radius-pill); background-color: #FFF9F7; border: 1px solid var(--admin-border); font-size: 12px; font-weight: 500; color: var(--admin-text);">
                <option value="30">30 hari</option>
                <option value="6m" selected>6 bulan</option>
                <option value="12m">12 bulan</option>
              </select>
            </div>
          </div>

          <!-- Area Grafik Garis Halus (SVG) -->
          <div id="chartContainer" class="sales-chart-container" style="position: relative; width: 100%;">
            <div id="chartSvgWrap" style="width: 100%; height: 100%;"></div>
            <div id="chartTooltip" style="position: absolute; display: none; pointer-events: none; background: #FFFFFF; border: 1px solid var(--admin-border); border-radius: 12px; padding: 8px 12px; box-shadow: var(--admin-shadow-card); z-index: 20; font-size: 11px; white-space: nowrap; transform: translate(-50%, -100%); margin-top: -8px;"></div>
          </div>
        </div>

        <!-- Kartu Kanan: Barang Terjual (Pie Chart) -->
        <div class="admin-card chart-card-pie">
          <div class="admin-card-header" style="align-items: flex-start; margin-bottom: 12px;">
            <div>
              <div class="admin-card-title">Barang Terjual</div>
              <div class="admin-card-desc" id="piePeriodDesc">6 bulan terakhir</div>
            </div>
          </div>

          <!-- Area Pie Chart Tengah -->
          <div style="position: relative; width: 100%; display: flex; flex-direction: column; align-items: center;">
            <div id="pieSvgWrap" style="width: 160px; height: 160px; position: relative;"></div>
            <div id="pieTooltip" style="position: absolute; display: none; pointer-events: none; background: #FFFFFF; border: 1px solid var(--admin-border); border-radius: 12px; padding: 8px 12px; box-shadow: var(--admin-shadow-card); z-index: 20; font-size: 11px; white-space: nowrap; transform: translate(-50%, -100%); margin-top: -8px;"></div>
            <div id="pieLegendWrap" style="width: 100%;"></div>
          </div>
        </div>
      </div>

      <!-- Baris Konten: 8 Kolom Kiri, 4 Kolom Kanan -->
      <div class="grid-12">
        <div class="col-8">
          <!-- Kartu Segera Restock -->
          <div class="admin-card">
            <div class="admin-card-header">
              <div class="admin-card-title-wrap">
                <div>
                  <div class="admin-card-title">Segera Restock</div>
                  <div class="admin-card-desc">Varian produk yang mendekati atau habis stok</div>
                </div>
              </div>
              <a href="#barang-masuk" class="btn btn-sm btn-ghost">Lihat semua</a>
            </div>

            <div class="table-responsive">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Produk & Varian</th>
                    <th>Stok Sekarang</th>
                    <th>Batas Minimum</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>
                      <div class="product-cell">
                        <img src="RiasKu Serif R App Icon.png" alt="Produk" class="table-thumb">
                        <div class="product-cell-info">
                          <span class="product-cell-name">Gentle Sunscreen Gel 50ml</span>
                          <span class="product-cell-brand">Standar 50ml</span>
                        </div>
                      </div>
                    </td>
                    <td><span class="status-pill status-stok-habis">0 pcs</span></td>
                    <td>5 pcs</td>
                    <td>
                      <a href="#barang-masuk" class="btn btn-xs btn-soft">Catat Barang Masuk</a>
                    </td>
                  </tr>
                  <tr>
                    <td>
                      <div class="product-cell">
                        <img src="RiasKu Serif R App Icon.png" alt="Produk" class="table-thumb">
                        <div class="product-cell-info">
                          <span class="product-cell-name">Dewy Cushion SPF 35</span>
                          <span class="product-cell-brand">02 Warm Beige</span>
                        </div>
                      </div>
                    </td>
                    <td><span class="status-pill status-segera-restock">6 pcs</span></td>
                    <td>10 pcs</td>
                    <td>
                      <a href="#barang-masuk" class="btn btn-xs btn-soft">Catat Barang Masuk</a>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Kartu Pesanan Terbaru -->
          <div class="admin-card">
            <div class="admin-card-header">
              <div class="admin-card-title-wrap">
                <div>
                  <div class="admin-card-title">Pesanan Terbaru</div>
                  <div class="admin-card-desc">5 pesanan terakhir dari pelanggan</div>
                </div>
              </div>
              <a href="#pesanan" class="btn btn-sm btn-ghost">Lihat semua</a>
            </div>

            <div class="table-responsive">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Kode</th>
                    <th>Penerima</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Tanggal</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  ${state.orders.slice(0, 5).map(o => `
                    <tr>
                      <td><strong style="color: var(--admin-accent);">${o.id}</strong></td>
                      <td>${o.recipient} <br><small style="color: var(--admin-text-muted);">${o.city}</small></td>
                      <td><strong>${formatRupiah(o.total)}</strong></td>
                      <td><span class="status-pill ${getOrderStatusClass(o.orderStatus)}">${o.orderStatus}</span></td>
                      <td>${o.date}</td>
                      <td>
                        <a href="#pesanan-detail" class="btn btn-xs btn-soft" onclick="window.activeOrderCode='${o.id}'">Lihat</a>
                      </td>
                    </tr>
                  `).join('')}
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="col-4">
          <!-- Kartu Aksi Cepat (Solid Burgundy) -->
          <div class="quick-action-card">
            <div>
              <div class="quick-action-label">Perlu ditindak</div>
              <div class="quick-action-value">${pendingOrdersCount}</div>
              <div class="quick-action-sub">pesanan menunggu tindakan</div>
            </div>
            <div class="quick-action-buttons">
              <a href="#pesanan" class="btn-quick-primary">
                Lihat Pesanan
              </a>
              <a href="#barang-masuk" class="btn-quick-secondary">
                Catat Barang Masuk
              </a>
            </div>
          </div>
        </div>
      </div>
    `;

    // Inisialisasi Grafik Penjualan & Barang Terjual (Section 5.1 Revisi)
    const periodSelect = document.getElementById('salesPeriodSelect');
    if (periodSelect) {
      const updateCharts = () => {
        const val = periodSelect.value;
        renderPenjualan(val);
        renderBarangTerjual(val);
      };

      periodSelect.addEventListener('change', updateCharts);
      updateCharts(); // Render bawaan 6 bulan

      // Responsif pada resize layar
      window.addEventListener('resize', () => {
        if (state.currentRoute === 'ringkasan') {
          updateCharts();
        }
      });
    }

    initMobileDrawer();
  }

  // -------------------------------------------------------------------------
  // 8.2 Produk (Daftar & Form)
  // -------------------------------------------------------------------------
  function renderProdukList(header, content) {
    header.innerHTML = `
      <div class="admin-header-row-1">
        <button class="admin-hamburger" id="btnHamburger" aria-label="Buka menu navigasi">
          <svg class="icon icon-md"><use href="#icon-menu"></use></svg>
        </button>
        <div class="admin-breadcrumb">
          <span>Admin</span>
          <span class="admin-breadcrumb-sep">></span>
          <span>Katalog</span>
          <span class="admin-breadcrumb-sep">></span>
          <span>Produk</span>
        </div>
      </div>
      <div class="admin-header-row-2">
        <div class="admin-title-area">
          <h1 class="admin-title">Produk</h1>
          <div class="admin-subtitle">Kelola katalog, harga, varian, dan foto kosmetik.</div>
        </div>
        <div class="admin-actions-area">
          <a href="#produk-tambah" class="btn btn-primary">
            <svg class="icon icon-sm"><use href="#icon-plus"></use></svg>
            Tambah Produk
          </a>
          <a href="login.html" target="_blank" class="btn btn-secondary">
            <svg class="icon icon-sm"><use href="#icon-external-link"></use></svg>
            Lihat Toko
          </a>
        </div>
      </div>
    `;

    content.innerHTML = `
      <div class="admin-card">
        <!-- Header Kartu: Kontrol Cari & Filter -->
        <div class="admin-card-header">
          <div class="admin-card-title-wrap">
            <span class="admin-card-title">Daftar Produk</span>
            <span class="status-pill status-dibatalkan">${state.products.length} data</span>
          </div>

          <div class="admin-card-controls">
            <div class="search-pill-wrap">
              <svg class="icon icon-sm search-icon"><use href="#icon-search"></use></svg>
              <input type="text" id="searchProduct" class="form-input" placeholder="Cari nama atau merek...">
            </div>

            <select id="filterCategory" class="form-select" style="width: auto; height: 36px; padding: 0 12px; border-radius: var(--admin-radius-pill);">
              <option value="">Semua Kategori</option>
              ${state.categories.map(c => `<option value="${c.name}">${c.name}</option>`).join('')}
            </select>

            <select id="filterStock" class="form-select" style="width: auto; height: 36px; padding: 0 12px; border-radius: var(--admin-radius-pill);">
              <option value="">Semua Stok</option>
              <option value="Tersedia">Tersedia</option>
              <option value="Pre-Order">Pre-Order</option>
              <option value="Stok Habis">Stok Habis</option>
            </select>

            <select id="filterStatus" class="form-select" style="width: auto; height: 36px; padding: 0 12px; border-radius: var(--admin-radius-pill);">
              <option value="">Semua Status</option>
              <option value="Aktif">Aktif</option>
              <option value="Nonaktif">Nonaktif</option>
            </select>
          </div>
        </div>

        <!-- Tabel Produk -->
        <div class="table-responsive">
          <table class="admin-table" id="productTable">
            <thead>
              <tr>
                <th>Produk</th>
                <th>Kategori</th>
                <th>Harga Eceran</th>
                <th>Pack/pcs</th>
                <th>Total Stok</th>
                <th>Label Stok</th>
                <th>Status</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody id="productTableBody">
              <!-- Rendered via JS -->
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="admin-pagination">
          <div>Menampilkan 1-${state.products.length} dari ${state.products.length} data</div>
          <div class="pagination-pages">
            <button class="page-btn active">1</button>
          </div>
        </div>
      </div>
    `;

    renderProductRows();

    // Event filter & cari
    const searchInput = document.getElementById('searchProduct');
    const catSelect = document.getElementById('filterCategory');
    const stockSelect = document.getElementById('filterStock');
    const statusSelect = document.getElementById('filterStatus');

    [searchInput, catSelect, stockSelect, statusSelect].forEach(el => {
      if (el) el.addEventListener('input', renderProductRows);
    });

    initMobileDrawer();
  }

  function renderProductRows() {
    const tbody = document.getElementById('productTableBody');
    if (!tbody) return;

    const query = (document.getElementById('searchProduct')?.value || '').toLowerCase();
    const cat = document.getElementById('filterCategory')?.value || '';
    const stock = document.getElementById('filterStock')?.value || '';
    const status = document.getElementById('filterStatus')?.value || '';

    const filtered = state.products.filter(p => {
      const matchQuery = p.name.toLowerCase().includes(query) || p.brand.toLowerCase().includes(query);
      const matchCat = !cat || p.category === cat;
      const stockLbl = getStockLabel(p).text;
      const matchStock = !stock || stockLbl === stock;
      const statusLbl = p.active ? 'Aktif' : 'Nonaktif';
      const matchStatus = !status || statusLbl === status;
      return matchQuery && matchCat && matchStock && matchStatus;
    });

    if (filtered.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="8">
            <div class="empty-state">
              <svg class="empty-icon"><use href="#icon-package"></use></svg>
              <div class="empty-title">Belum ada produk</div>
              <div class="empty-sub">Tidak ditemukan produk yang sesuai dengan filter pencarian Anda.</div>
              <a href="#produk-tambah" class="btn btn-sm btn-primary">
                <svg class="icon icon-sm"><use href="#icon-plus"></use></svg>
                Tambah Produk
              </a>
            </div>
          </td>
        </tr>
      `;
      return;
    }

    tbody.innerHTML = filtered.map(p => {
      const totalStock = p.variants.reduce((acc, v) => acc + (v.stock || 0), 0);
      const stockLabel = getStockLabel(p);
      const packText = p.packAvailable ? formatRupiah(p.packPricePerPcs) : '-';

      return `
        <tr>
          <td>
            <div class="product-cell">
              <img src="${p.image}" alt="${p.name}" class="table-thumb">
              <div class="product-cell-info">
                <span class="product-cell-name">${p.name}</span>
                <span class="product-cell-brand">${p.brand}</span>
              </div>
            </div>
          </td>
          <td>${p.category}</td>
          <td><strong>${formatRupiah(p.retailPrice)}</strong></td>
          <td>${packText}</td>
          <td>${totalStock} pcs</td>
          <td><span class="status-pill ${stockLabel.class}">${stockLabel.text}</span></td>
          <td>
            <span class="status-pill ${p.active ? 'status-aktif' : 'status-nonaktif'}">
              ${p.active ? 'Aktif' : 'Nonaktif'}
            </span>
          </td>
          <td>
            <div class="table-actions">
              <button class="btn btn-xs btn-soft" onclick="window.editProduct(${p.id})">Ubah</button>
              <button class="btn btn-xs ${p.active ? 'btn-soft-danger' : 'btn-soft-success'}" onclick="window.toggleProductStatus(${p.id})">
                ${p.active ? 'Nonaktifkan' : 'Aktifkan'}
              </button>
            </div>
          </td>
        </tr>
      `;
    }).join('');
  }

  // Aksi Ubah Produk
  window.editProduct = function (id) {
    state.activeProductFormId = id;
    navigateTo('produk-form');
  };

  // Dialog Nonaktifkan / Aktifkan Produk
  window.toggleProductStatus = function (id) {
    const product = state.products.find(p => p.id === id);
    if (!product) return;

    if (product.active) {
      // Buka dialog konfirmasi
      showConfirmDialog({
        iconClass: 'modal-icon-danger',
        iconSymbol: 'icon-alert-triangle',
        title: 'Nonaktifkan Produk?',
        descHtml: `Produk <strong>${product.name}</strong> tidak akan tampil di etalase toko pelanggan.`,
        confirmText: 'Nonaktifkan',
        confirmClass: 'btn-danger',
        onConfirm: () => {
          product.active = false;
          showToast(`Produk ${product.name} berhasil dinonaktifkan.`, 'success');
          renderProductRows();
        }
      });
    } else {
      product.active = true;
      showToast(`Produk ${product.name} berhasil diaktifkan kembali.`, 'success');
      renderProductRows();
    }
  };

  // -------------------------------------------------------------------------
  // 8.3 Form Tambah / Ubah Produk (Pola Form 8+4)
  // -------------------------------------------------------------------------
  function renderProdukForm(header, content) {
    const isEdit = state.activeProductFormId !== null && state.currentRoute === 'produk-form';
    const product = isEdit ? state.products.find(p => p.id === state.activeProductFormId) : null;

    const pageTitle = isEdit ? 'Ubah Produk' : 'Tambah Produk';

    header.innerHTML = `
      <div class="admin-header-row-1">
        <button class="admin-hamburger" id="btnHamburger" aria-label="Buka menu navigasi">
          <svg class="icon icon-md"><use href="#icon-menu"></use></svg>
        </button>
        <div class="admin-breadcrumb">
          <a href="#ringkasan">Admin</a>
          <span class="admin-breadcrumb-sep">></span>
          <a href="#produk">Katalog</a>
          <span class="admin-breadcrumb-sep">></span>
          <a href="#produk">Produk</a>
          <span class="admin-breadcrumb-sep">></span>
          <span>${pageTitle}</span>
        </div>
      </div>
      <div class="admin-header-row-2">
        <div class="admin-title-area">
          <h1 class="admin-title">${pageTitle}</h1>
          <div class="admin-subtitle">Lengkapi rincian informasi, harga, dan varian produk.</div>
        </div>
        <div class="admin-actions-area">
          <a href="#produk" class="btn btn-secondary">Batal</a>
        </div>
      </div>
    `;

    // Data form awal
    const nameVal = product ? product.name : '';
    const brandVal = product ? product.brand : 'RiasKu Glow';
    const catVal = product ? product.category : 'Bibir';
    const modalVal = product ? product.modalPrice : 28000;
    const retailVal = product ? product.retailPrice : 38000;
    const packActive = product ? product.packAvailable : false;
    const packSizeVal = product ? product.packSize : 6;
    const packPriceVal = product ? product.packPricePerPcs : 34000;
    const prodActive = product ? product.active : true;
    const preOrderActive = product ? product.canPreOrder : false;
    const preOrderDaysVal = product ? product.preOrderDays : 3;

    content.innerHTML = `
      <form id="productMainForm">
        <div class="grid-12">
          <!-- Kolom Utama (8 Kolom) -->
          <div class="col-8">
            <!-- Kartu Informasi Produk -->
            <div class="admin-card">
              <div class="admin-card-header">
                <div class="admin-card-title">Informasi Produk</div>
              </div>

              <div class="form-group">
                <label class="form-label">Kategori <span class="required">*</span></label>
                <select class="form-select" id="formCat" required>
                  ${state.categories.map(c => `<option value="${c.name}" ${c.name === catVal ? 'selected' : ''}>${c.name}</option>`).join('')}
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">Nama Produk <span class="required">*</span></label>
                <input type="text" id="formName" class="form-input" value="${nameVal}" placeholder="Contoh: Dewy Lip Tint Velvet" required>
              </div>

              <div class="form-group">
                <label class="form-label">Merek <span class="required">*</span></label>
                <input type="text" id="formBrand" class="form-input" value="${brandVal}" placeholder="Contoh: RiasKu Glow" required>
              </div>

              <div class="form-group">
                <label class="form-label">Ingredients Singkat</label>
                <textarea id="formIngredients" class="form-textarea" placeholder="Komposisi utama, vitamin E, jojoba oil, dll.">Aqua, Jojoba Oil, Tocopherol (Vitamin E), Glycerin, Pigment Rose Extract.</textarea>
              </div>

              <div class="grid-12" style="margin-bottom: 0;">
                <div class="col-6">
                  <div class="form-group">
                    <label class="form-label">Nomor BPOM (Opsional)</label>
                    <input type="text" id="formBpom" class="form-input" value="NA18231209876" placeholder="Contoh: NA18231209876">
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-group">
                    <label class="form-label">Berat (Gram) <span class="required">*</span></label>
                    <input type="number" id="formWeight" class="form-input" value="50" required>
                    <div class="form-help">Dipakai untuk menghitung ongkos kirim ekspedisi.</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Kartu Harga & Margin -->
            <div class="admin-card">
              <div class="admin-card-header">
                <div class="admin-card-title">Harga & Aturan Pack</div>
              </div>

              <div class="grid-12">
                <div class="col-6">
                  <div class="form-group">
                    <label class="form-label">Harga Modal <span class="required">*</span></label>
                    <div class="input-rupiah-wrap">
                      <span class="prefix">Rp</span>
                      <input type="number" id="formModalPrice" class="form-input" value="${modalVal}" required>
                    </div>
                  </div>
                </div>

                <div class="col-6">
                  <div class="form-group">
                    <label class="form-label">Harga Eceran <span class="required">*</span></label>
                    <div class="input-rupiah-wrap">
                      <span class="prefix">Rp</span>
                      <input type="number" id="formRetailPrice" class="form-input" value="${retailVal}" required>
                    </div>
                    <div class="form-help">Saran UMKM: Modal + Rp1.000 s/d Rp2.000 untuk reseller atau margin standar.</div>
                  </div>
                </div>
              </div>

              <!-- Peringatan Margin Dinamis -->
              <div id="marginInfoBox" style="margin-top: 4px;"></div>

              <div class="form-group" style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--admin-border);">
                <label class="toggle-wrap">
                  <input type="checkbox" id="formPackToggle" class="toggle-input" ${packActive ? 'checked' : ''}>
                  <div class="toggle-switch"><div class="toggle-knob"></div></div>
                  <span class="toggle-label">Dijual per pack (Grosir / Reseller)</span>
                </label>
              </div>

              <div id="packFields" class="grid-12" style="display: ${packActive ? 'grid' : 'none'}; margin-top: 12px;">
                <div class="col-6">
                  <div class="form-group">
                    <label class="form-label">Isi per Pack (pcs) <span class="required">*</span></label>
                    <input type="number" id="formPackSize" class="form-input" value="${packSizeVal}">
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-group">
                    <label class="form-label">Harga per pcs saat Beli Pack <span class="required">*</span></label>
                    <div class="input-rupiah-wrap">
                      <span class="prefix">Rp</span>
                      <input type="number" id="formPackPrice" class="form-input" value="${packPriceVal}">
                    </div>
                    <div id="packPriceError" class="form-error" style="display: none;">
                      Harga per pcs pack harus di antara harga modal dan harga eceran!
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Kartu Varian dan Stok -->
            <div class="admin-card">
              <div class="admin-card-header">
                <div>
                  <div class="admin-card-title">Varian dan Stok</div>
                  <div class="admin-card-desc">Tentukan nama varian, stok, dan status aktif. Untuk menambah stok dari grosir, gunakan menu Barang Masuk.</div>
                </div>
              </div>

              <div class="repeater-list" id="variantList">
                ${(product && product.variants.length > 0) ? product.variants.map((v, i) => `
                  <div class="repeater-row" data-index="${i}">
                    <input type="text" class="form-input var-name" value="${v.name}" placeholder="Nama varian" style="flex: 2;" required>
                    <input type="number" class="form-input var-stock" value="${v.stock}" placeholder="Stok" style="flex: 1;" required>
                    <label class="toggle-wrap" style="flex-shrink: 0;">
                      <input type="checkbox" class="toggle-input var-active" ${v.active ? 'checked' : ''}>
                      <div class="toggle-switch"><div class="toggle-knob"></div></div>
                    </label>
                    <button type="button" class="btn btn-xs btn-ghost btn-del-variant" style="color: var(--admin-danger);">
                      <svg class="icon icon-sm"><use href="#icon-trash-2"></use></svg>
                    </button>
                  </div>
                `).join('') : `
                  <div class="repeater-row">
                    <input type="text" class="form-input var-name" value="Standar" placeholder="Nama varian" style="flex: 2;" required>
                    <input type="number" class="form-input var-stock" value="10" placeholder="Stok" style="flex: 1;" required>
                    <label class="toggle-wrap" style="flex-shrink: 0;">
                      <input type="checkbox" class="toggle-input var-active" checked>
                      <div class="toggle-switch"><div class="toggle-knob"></div></div>
                    </label>
                    <button type="button" class="btn btn-xs btn-ghost btn-del-variant" style="color: var(--admin-danger);">
                      <svg class="icon icon-sm"><use href="#icon-trash-2"></use></svg>
                    </button>
                  </div>
                `}
              </div>

              <button type="button" id="btnAddVariant" class="btn btn-sm btn-secondary" style="margin-top: 14px;">
                <svg class="icon icon-sm"><use href="#icon-plus"></use></svg>
                Tambah Varian
              </button>
            </div>
          </div>

          <!-- Kolom Samping (4 Kolom) -->
          <div class="col-4">
            <!-- Kartu Status & Pre-Order -->
            <div class="admin-card">
              <div class="admin-card-header">
                <div class="admin-card-title">Status Produk</div>
              </div>

              <div class="form-group">
                <label class="toggle-wrap">
                  <input type="checkbox" id="formActiveToggle" class="toggle-input" ${prodActive ? 'checked' : ''}>
                  <div class="toggle-switch"><div class="toggle-knob"></div></div>
                  <span class="toggle-label">Produk Aktif</span>
                </label>
                <div class="form-help">Produk yang nonaktif tidak dapat dilihat atau dibeli oleh pelanggan.</div>
              </div>

              <div class="form-group" style="padding-top: 14px; border-top: 1px solid var(--admin-border);">
                <label class="toggle-wrap">
                  <input type="checkbox" id="formPreOrderToggle" class="toggle-input" ${preOrderActive ? 'checked' : ''}>
                  <div class="toggle-switch"><div class="toggle-knob"></div></div>
                  <span class="toggle-label">Bisa Pre-Order</span>
                </label>
                <div class="form-help">Pelanggan dapat memesan produk saat stok habis.</div>
              </div>

              <div id="preOrderFields" class="form-group" style="display: ${preOrderActive ? 'block' : 'none'};">
                <label class="form-label">Estimasi Pre-Order <span class="required">*</span></label>
                <select id="formPreOrderDays" class="form-select">
                  <option value="1" ${preOrderDaysVal === 1 ? 'selected' : ''}>1 Hari</option>
                  <option value="2" ${preOrderDaysVal === 2 ? 'selected' : ''}>2 Hari</option>
                  <option value="3" ${preOrderDaysVal === 3 ? 'selected' : ''}>3 Hari</option>
                  <option value="4" ${preOrderDaysVal === 4 ? 'selected' : ''}>4 Hari</option>
                  <option value="5" ${preOrderDaysVal === 5 ? 'selected' : ''}>5 Hari</option>
                  <option value="6" ${preOrderDaysVal === 6 ? 'selected' : ''}>6 Hari</option>
                </select>
              </div>
            </div>

            <!-- Kartu Foto Produk -->
            <div class="admin-card">
              <div class="admin-card-header">
                <div class="admin-card-title">Foto Produk</div>
              </div>

              <div class="photo-grid" id="photoGrid">
                <div class="photo-item">
                  <img src="RiasKu Serif R App Icon.png" alt="Foto Produk">
                  <span class="photo-badge-main">Utama</span>
                  <button type="button" class="photo-del-btn" title="Hapus foto">
                    <svg class="icon icon-sm"><use href="#icon-x"></use></svg>
                  </button>
                </div>
                <button type="button" class="photo-add-btn">
                  <svg class="icon icon-md"><use href="#icon-camera"></use></svg>
                  <span>+ Tambah foto</span>
                </button>
              </div>
              <div class="form-help" style="margin-top: 12px;">Watermark toko ditambahkan otomatis saat foto ditampilkan di toko.</div>
            </div>
          </div>
        </div>

        <!-- Bilah Aksi Sticky di Bawah -->
        <div class="form-sticky-bar">
          <a href="#produk" class="btn btn-secondary">Batal</a>
          <button type="submit" class="btn btn-primary">Simpan Produk</button>
        </div>
      </form>
    `;

    // Kalkulasi Margin Dinamis & Validasi Pack
    const modalInput = document.getElementById('formModalPrice');
    const retailInput = document.getElementById('formRetailPrice');
    const marginBox = document.getElementById('marginInfoBox');
    const packToggle = document.getElementById('formPackToggle');
    const packFields = document.getElementById('packFields');
    const packPriceInput = document.getElementById('formPackPrice');
    const packPriceErr = document.getElementById('packPriceError');
    const preOrderToggle = document.getElementById('formPreOrderToggle');
    const preOrderFields = document.getElementById('preOrderFields');

    function updateMarginCalculation() {
      const modal = parseFloat(modalInput.value) || 0;
      const retail = parseFloat(retailInput.value) || 0;
      const margin = retail - modal;

      let msgHtml = `
        <div style="font-size: 12px; color: var(--admin-accent); font-weight: 500;">
          Margin per pcs: <strong>${formatRupiah(margin)}</strong>
        </div>
      `;

      if (margin < 1000 || margin > 2000) {
        msgHtml += `
          <div class="form-warning">
            Perhatian: Margin per pcs di luar rentang saran UMKM (Rp1.000 - Rp2.000). Pastikan margin sudah sesuai strategi harga toko.
          </div>
        `;
      }

      marginBox.innerHTML = msgHtml;

      // Validasi Harga Pack
      if (packToggle.checked) {
        const packPrice = parseFloat(packPriceInput.value) || 0;
        if (packPrice <= modal || packPrice >= retail) {
          packPriceErr.style.display = 'block';
        } else {
          packPriceErr.style.display = 'none';
        }
      }
    }

    modalInput.addEventListener('input', updateMarginCalculation);
    retailInput.addEventListener('input', updateMarginCalculation);
    packPriceInput.addEventListener('input', updateMarginCalculation);
    updateMarginCalculation();

    packToggle.addEventListener('change', () => {
      packFields.style.display = packToggle.checked ? 'grid' : 'none';
      updateMarginCalculation();
    });

    preOrderToggle.addEventListener('change', () => {
      preOrderFields.style.display = preOrderToggle.checked ? 'block' : 'none';
    });

    // Tambah Varian Dinamis
    const variantList = document.getElementById('variantList');
    document.getElementById('btnAddVariant').addEventListener('click', () => {
      const row = document.createElement('div');
      row.className = 'repeater-row';
      row.innerHTML = `
        <input type="text" class="form-input var-name" placeholder="Nama varian" style="flex: 2;" required>
        <input type="number" class="form-input var-stock" value="0" placeholder="Stok" style="flex: 1;" required>
        <label class="toggle-wrap" style="flex-shrink: 0;">
          <input type="checkbox" class="toggle-input var-active" checked>
          <div class="toggle-switch"><div class="toggle-knob"></div></div>
        </label>
        <button type="button" class="btn btn-xs btn-ghost btn-del-variant" style="color: var(--admin-danger);">
          <svg class="icon icon-sm"><use href="#icon-trash-2"></use></svg>
        </button>
      `;
      variantList.appendChild(row);
      attachVariantDelEvents();
    });

    function attachVariantDelEvents() {
      document.querySelectorAll('.btn-del-variant').forEach(btn => {
        btn.onclick = () => {
          const row = btn.closest('.repeater-row');
          if (variantList.querySelectorAll('.repeater-row').length > 1) {
            row.remove();
          } else {
            showToast('Produk wajib memiliki minimal 1 varian.', 'danger');
          }
        };
      });
    }
    attachVariantDelEvents();

    // Submit Form Simpan
    document.getElementById('productMainForm').addEventListener('submit', (e) => {
      e.preventDefault();

      const name = document.getElementById('formName').value.trim();
      const brand = document.getElementById('formBrand').value.trim();
      const category = document.getElementById('formCat').value;
      const modal = parseFloat(modalInput.value);
      const retail = parseFloat(retailInput.value);
      const isPack = packToggle.checked;
      const packSize = parseInt(document.getElementById('formPackSize').value) || 0;
      const packPrice = parseFloat(packPriceInput.value) || 0;
      const active = document.getElementById('formActiveToggle').checked;
      const canPO = preOrderToggle.checked;
      const poDays = parseInt(document.getElementById('formPreOrderDays').value) || 3;

      if (isPack && (packPrice <= modal || packPrice >= retail)) {
        showToast('Harga pack per pcs harus berada di antara modal dan eceran.', 'danger');
        return;
      }

      // Kumpulkan Varian
      const variants = [];
      variantList.querySelectorAll('.repeater-row').forEach(row => {
        variants.push({
          name: row.querySelector('.var-name').value.trim() || 'Standar',
          stock: parseInt(row.querySelector('.var-stock').value) || 0,
          active: row.querySelector('.var-active').checked
        });
      });

      if (isEdit) {
        const prod = state.products.find(p => p.id === state.activeProductFormId);
        if (prod) {
          prod.name = name;
          prod.brand = brand;
          prod.category = category;
          prod.modalPrice = modal;
          prod.retailPrice = retail;
          prod.packAvailable = isPack;
          prod.packSize = packSize;
          prod.packPricePerPcs = packPrice;
          prod.active = active;
          prod.canPreOrder = canPO;
          prod.preOrderDays = poDays;
          prod.variants = variants;
        }
        showToast(`Produk ${name} berhasil diperbarui.`, 'success');
      } else {
        const newProduct = {
          id: Date.now(),
          name,
          brand,
          category,
          modalPrice: modal,
          retailPrice: retail,
          packAvailable: isPack,
          packSize,
          packPricePerPcs: packPrice,
          active,
          canPreOrder: canPO,
          preOrderDays: poDays,
          image: 'RiasKu Serif R App Icon.png',
          variants
        };
        state.products.unshift(newProduct);
        showToast(`Produk ${name} berhasil ditambahkan.`, 'success');
      }

      state.activeProductFormId = null;
      navigateTo('produk');
    });

    initMobileDrawer();
  }

  // -------------------------------------------------------------------------
  // 8.4 Kategori
  // -------------------------------------------------------------------------
  function renderKategori(header, content) {
    header.innerHTML = `
      <div class="admin-header-row-1">
        <button class="admin-hamburger" id="btnHamburger" aria-label="Buka menu navigasi">
          <svg class="icon icon-md"><use href="#icon-menu"></use></svg>
        </button>
        <div class="admin-breadcrumb">
          <span>Admin</span>
          <span class="admin-breadcrumb-sep">></span>
          <span>Katalog</span>
          <span class="admin-breadcrumb-sep">></span>
          <span>Kategori</span>
        </div>
      </div>
      <div class="admin-header-row-2">
        <div class="admin-title-area">
          <h1 class="admin-title">Kategori</h1>
          <div class="admin-subtitle">Pengelompokan produk etalase kosmetik RiasKu.</div>
        </div>
        <div class="admin-actions-area">
          <button id="btnOpenAddCategory" class="btn btn-primary">
            <svg class="icon icon-sm"><use href="#icon-plus"></use></svg>
            Tambah Kategori
          </button>
        </div>
      </div>
    `;

    content.innerHTML = `
      <div class="admin-card">
        <div class="admin-card-header">
          <div class="admin-card-title-wrap">
            <span class="admin-card-title">Daftar Kategori</span>
            <span class="status-pill status-dibatalkan">${state.categories.length} kategori</span>
          </div>
        </div>

        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Nama Kategori</th>
                <th>Jumlah Produk</th>
                <th>Tanggal Dibuat</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              ${state.categories.map(c => `
                <tr>
                  <td><strong>${c.name}</strong></td>
                  <td>${c.count} produk</td>
                  <td>${c.createdAt}</td>
                  <td>
                    <div class="table-actions">
                      <button class="btn btn-xs btn-soft" onclick="window.editCategory(${c.id})">Ubah</button>
                      <button class="btn btn-xs ${c.count > 0 ? 'btn-ghost' : 'btn-soft-danger'}" 
                              ${c.count > 0 ? 'disabled title="Pindahkan atau nonaktifkan produknya lebih dulu."' : ''} 
                              onclick="window.deleteCategory(${c.id})" 
                              style="${c.count > 0 ? 'opacity: 0.45; cursor: not-allowed;' : ''}">
                        Hapus
                      </button>
                    </div>
                  </td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      </div>

      <!-- Modal Tambah/Ubah Kategori -->
      <dialog id="categoryModal" class="admin-modal">
        <div class="modal-title" id="catModalTitle">Tambah Kategori</div>
        <div class="modal-desc">Masukkan nama kategori produk kosmetik baru.</div>
        <form id="categoryForm">
          <div class="form-group">
            <label class="form-label">Nama Kategori <span class="required">*</span></label>
            <input type="text" id="catNameInput" class="form-input" placeholder="Contoh: Skincare Wajah" required>
            <div id="catNameError" class="form-error" style="display: none;">Nama kategori sudah dipakai!</div>
          </div>
          <div class="modal-actions" style="margin-top: 20px;">
            <button type="button" class="btn btn-sm btn-secondary" onclick="document.getElementById('categoryModal').close()">Batal</button>
            <button type="submit" class="btn btn-sm btn-primary">Simpan</button>
          </div>
        </form>
      </dialog>
    `;

    const catModal = document.getElementById('categoryModal');
    let editingCatId = null;

    document.getElementById('btnOpenAddCategory').addEventListener('click', () => {
      editingCatId = null;
      document.getElementById('catModalTitle').textContent = 'Tambah Kategori';
      document.getElementById('catNameInput').value = '';
      document.getElementById('catNameError').style.display = 'none';
      catModal.showModal();
    });

    window.editCategory = function (id) {
      const cat = state.categories.find(c => c.id === id);
      if (!cat) return;
      editingCatId = id;
      document.getElementById('catModalTitle').textContent = 'Ubah Kategori';
      document.getElementById('catNameInput').value = cat.name;
      document.getElementById('catNameError').style.display = 'none';
      catModal.showModal();
    };

    window.deleteCategory = function (id) {
      const cat = state.categories.find(c => c.id === id);
      if (!cat || cat.count > 0) return;

      showConfirmDialog({
        iconClass: 'modal-icon-danger',
        iconSymbol: 'icon-trash-2',
        title: 'Hapus Kategori?',
        descHtml: `Kategori <strong>${cat.name}</strong> akan dihapus permanen.`,
        confirmText: 'Hapus',
        confirmClass: 'btn-danger',
        onConfirm: () => {
          state.categories = state.categories.filter(c => c.id !== id);
          showToast(`Kategori ${cat.name} berhasil dihapus.`, 'success');
          renderKategori(header, content);
        }
      });
    };

    document.getElementById('categoryForm').addEventListener('submit', (e) => {
      e.preventDefault();
      const val = document.getElementById('catNameInput').value.trim();
      const isExist = state.categories.some(c => c.name.toLowerCase() === val.toLowerCase() && c.id !== editingCatId);

      if (isExist) {
        document.getElementById('catNameError').style.display = 'block';
        return;
      }

      if (editingCatId) {
        const cat = state.categories.find(c => c.id === editingCatId);
        if (cat) cat.name = val;
        showToast('Kategori berhasil diperbarui.', 'success');
      } else {
        state.categories.push({
          id: Date.now(),
          name: val,
          count: 0,
          createdAt: '6 Nov 2026'
        });
        showToast(`Kategori ${val} berhasil ditambahkan.`, 'success');
      }

      catModal.close();
      renderKategori(header, content);
    });

    initMobileDrawer();
  }

  // -------------------------------------------------------------------------
  // 8.5 Ulasan
  // -------------------------------------------------------------------------
  function renderUlasan(header, content) {
    header.innerHTML = `
      <div class="admin-header-row-1">
        <button class="admin-hamburger" id="btnHamburger" aria-label="Buka menu navigasi">
          <svg class="icon icon-md"><use href="#icon-menu"></use></svg>
        </button>
        <div class="admin-breadcrumb">
          <span>Admin</span>
          <span class="admin-breadcrumb-sep">></span>
          <span>Katalog</span>
          <span class="admin-breadcrumb-sep">></span>
          <span>Ulasan</span>
        </div>
      </div>
      <div class="admin-header-row-2">
        <div class="admin-title-area">
          <h1 class="admin-title">Ulasan</h1>
          <div class="admin-subtitle">Tinjau dan moderasi ulasan produk dari pelanggan.</div>
        </div>
      </div>
    `;

    content.innerHTML = `
      <div class="admin-card">
        <div class="admin-card-header">
          <div class="admin-card-title-wrap">
            <span class="admin-card-title">Daftar Ulasan</span>
            <span class="status-pill status-dibatalkan">${state.reviews.length} ulasan</span>
          </div>

          <div class="admin-card-controls">
            <select id="filterReviewRating" class="form-select" style="width: auto; height: 36px; padding: 0 12px; border-radius: var(--admin-radius-pill);">
              <option value="">Semua Rating</option>
              <option value="5">Bintang 5</option>
              <option value="4">Bintang 4</option>
              <option value="3">Bintang 3</option>
              <option value="2">Bintang 2</option>
              <option value="1">Bintang 1</option>
            </select>

            <select id="filterReviewStatus" class="form-select" style="width: auto; height: 36px; padding: 0 12px; border-radius: var(--admin-radius-pill);">
              <option value="">Semua Status</option>
              <option value="Tampil">Tampil</option>
              <option value="Disembunyikan">Disembunyikan</option>
            </select>
          </div>
        </div>

        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Produk & Varian</th>
                <th>Pemberi Ulasan</th>
                <th>Rating</th>
                <th>Komentar</th>
                <th>Foto</th>
                <th>Tanggal</th>
                <th>Status</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody id="reviewTableBody">
              <!-- Rendered via JS -->
            </tbody>
          </table>
        </div>
      </div>
    `;

    function renderReviewTable() {
      const tbody = document.getElementById('reviewTableBody');
      if (!tbody) return;

      const ratingFilter = document.getElementById('filterReviewRating').value;
      const statusFilter = document.getElementById('filterReviewStatus').value;

      const filtered = state.reviews.filter(r => {
        const matchRating = !ratingFilter || r.rating === parseInt(ratingFilter);
        const matchStatus = !statusFilter || r.status === statusFilter;
        return matchRating && matchStatus;
      });

      if (filtered.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="8">
              <div class="empty-state">
                <svg class="empty-icon"><use href="#icon-star"></use></svg>
                <div class="empty-title">Belum ada ulasan</div>
                <div class="empty-sub">Ulasan pelanggan akan muncul di sini setelah pesanan selesai.</div>
              </div>
            </td>
          </tr>
        `;
        return;
      }

      tbody.innerHTML = filtered.map(r => `
        <tr>
          <td><strong>${r.product}</strong></td>
          <td>${r.reviewer}</td>
          <td>
            <div class="star-rating">
              ${[1, 2, 3, 4, 5].map(star => `
                <svg class="star ${star <= r.rating ? 'filled' : ''}"><use href="#icon-star"></use></svg>
              `).join('')}
              <span class="star-score">${r.rating}.0</span>
            </div>
          </td>
          <td>
            <div style="max-width: 260px; line-height: 1.4;">
              ${r.expanded ? r.comment : (r.comment.length > 60 ? r.comment.substring(0, 60) + '...' : r.comment)}
              ${r.expanded ? `
                <div style="margin-top: 8px; display: flex; gap: 8px; align-items: center;">
                  <span style="font-size: 11px; color: var(--admin-text-muted);">Pesanan: <a href="#pesanan-detail" style="font-weight: 600;">${r.orderCode}</a></span>
                  ${r.photosCount > 0 ? `<img src="RiasKu Serif R App Icon.png" alt="Foto ulasan" style="width: 48px; height: 48px; border-radius: 8px; border: 1px solid var(--admin-border);">` : ''}
                </div>
              ` : ''}
            </div>
          </td>
          <td>${r.photosCount} foto</td>
          <td>${r.date}</td>
          <td>
            <span class="status-pill ${r.status === 'Tampil' ? 'status-tampil' : 'status-disembunyikan'}">
              ${r.status}
            </span>
          </td>
          <td>
            <div class="table-actions">
              <button class="btn btn-xs btn-soft" onclick="window.toggleReviewExpand(${r.id})">
                ${r.expanded ? 'Tutup' : 'Lihat'}
              </button>
              ${r.status === 'Tampil' ? `
                <button class="btn btn-xs btn-soft-danger" onclick="window.hideReview(${r.id})">
                  Sembunyikan
                </button>
              ` : ''}
            </div>
          </td>
        </tr>
      `).join('');
    }

    window.toggleReviewExpand = function (id) {
      const r = state.reviews.find(item => item.id === id);
      if (r) {
        r.expanded = !r.expanded;
        renderReviewTable();
      }
    };

    window.hideReview = function (id) {
      const r = state.reviews.find(item => item.id === id);
      if (!r) return;

      showConfirmDialog({
        iconClass: 'modal-icon-danger',
        iconSymbol: 'icon-eye-off',
        title: 'Sembunyikan ulasan ini?',
        descHtml: `Ulasan dari <strong>${r.reviewer}</strong> tidak akan tampil lagi di halaman detail produk.`,
        confirmText: 'Sembunyikan',
        confirmClass: 'btn-danger',
        onConfirm: () => {
          r.status = 'Disembunyikan';
          showToast('Ulasan berhasil disembunyikan.', 'success');
          renderReviewTable();
        }
      });
    };

    document.getElementById('filterReviewRating').addEventListener('change', renderReviewTable);
    document.getElementById('filterReviewStatus').addEventListener('change', renderReviewTable);
    renderReviewTable();

    initMobileDrawer();
  }

  // -------------------------------------------------------------------------
  // 8.6 Pesanan (Daftar & Detail)
  // -------------------------------------------------------------------------
  function renderPesananList(header, content) {
    header.innerHTML = `
      <div class="admin-header-row-1">
        <button class="admin-hamburger" id="btnHamburger" aria-label="Buka menu navigasi">
          <svg class="icon icon-md"><use href="#icon-menu"></use></svg>
        </button>
        <div class="admin-breadcrumb">
          <span>Admin</span>
          <span class="admin-breadcrumb-sep">></span>
          <span>Pesanan</span>
        </div>
      </div>
      <div class="admin-header-row-2">
        <div class="admin-title-area">
          <h1 class="admin-title">Pesanan</h1>
          <div class="admin-subtitle">Daftar transaksi pesanan online dan pelacakan resi.</div>
        </div>
      </div>
    `;

    // Hitung Pesanan Tiap Status
    const counts = {
      all: state.orders.length,
      menunggu: state.orders.filter(o => o.orderStatus === 'Menunggu Pembayaran').length,
      dibayar: state.orders.filter(o => o.orderStatus === 'Dibayar').length,
      diproses: state.orders.filter(o => o.orderStatus === 'Diproses').length,
      dikirim: state.orders.filter(o => o.orderStatus === 'Dikirim').length,
      selesai: state.orders.filter(o => o.orderStatus === 'Selesai').length,
      dibatalkan: state.orders.filter(o => o.orderStatus === 'Dibatalkan').length
    };

    content.innerHTML = `
      <div class="admin-card">
        <!-- Deret Tab Status Pesanan -->
        <div class="status-tabs-wrap">
          <button class="tab-pill active" data-tab="all">Semua <span class="tab-count">(${counts.all})</span></button>
          <button class="tab-pill" data-tab="Menunggu Pembayaran">Menunggu Bayar <span class="tab-count">(${counts.menunggu})</span></button>
          <button class="tab-pill" data-tab="Dibayar">Dibayar <span class="tab-count">(${counts.dibayar})</span></button>
          <button class="tab-pill" data-tab="Diproses">Diproses <span class="tab-count">(${counts.diproses})</span></button>
          <button class="tab-pill" data-tab="Dikirim">Dikirim <span class="tab-count">(${counts.dikirim})</span></button>
          <button class="tab-pill" data-tab="Selesai">Selesai <span class="tab-count">(${counts.selesai})</span></button>
          <button class="tab-pill" data-tab="Dibatalkan">Dibatalkan <span class="tab-count">(${counts.dibatalkan})</span></button>
        </div>

        <!-- Filter & Cari -->
        <div class="admin-card-header">
          <div class="search-pill-wrap" style="width: 280px;">
            <svg class="icon icon-sm search-icon"><use href="#icon-search"></use></svg>
            <input type="text" id="searchOrder" class="form-input" placeholder="Cari kode atau penerima...">
          </div>

          <div class="admin-card-controls">
            <select id="filterPayment" class="form-select" style="width: auto; height: 36px; padding: 0 12px; border-radius: var(--admin-radius-pill);">
              <option value="">Semua Pembayaran</option>
              <option value="COD (Uang Tunai)">COD (Uang Tunai)</option>
              <option value="QRIS">QRIS</option>
              <option value="Transfer Bank">Transfer Bank</option>
            </select>

            <select id="filterShipping" class="form-select" style="width: auto; height: 36px; padding: 0 12px; border-radius: var(--admin-radius-pill);">
              <option value="">Semua Pengiriman</option>
              <option value="Diantar Penjual">Diantar Penjual</option>
              <option value="JNE">JNE</option>
              <option value="J&T">J&T</option>
            </select>
          </div>
        </div>

        <!-- Tabel Pesanan -->
        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Kode Pesanan</th>
                <th>Penerima & Kota</th>
                <th>Item</th>
                <th>Total Bayar</th>
                <th>Metode Bayar</th>
                <th>Status Bayar</th>
                <th>Status Pesanan</th>
                <th>Tanggal</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody id="orderTableBody">
              <!-- Rendered via JS -->
            </tbody>
          </table>
        </div>
      </div>
    `;

    let activeTab = 'all';

    function renderOrderRows() {
      const tbody = document.getElementById('orderTableBody');
      if (!tbody) return;

      const q = (document.getElementById('searchOrder')?.value || '').toLowerCase();
      const payFilter = document.getElementById('filterPayment')?.value || '';
      const shipFilter = document.getElementById('filterShipping')?.value || '';

      const filtered = state.orders.filter(o => {
        const matchTab = activeTab === 'all' || o.orderStatus === activeTab;
        const matchQ = o.id.toLowerCase().includes(q) || o.recipient.toLowerCase().includes(q);
        const matchPay = !payFilter || o.paymentMethod.includes(payFilter);
        const matchShip = !shipFilter || o.courier === shipFilter;
        return matchTab && matchQ && matchPay && matchShip;
      });

      if (filtered.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="9">
              <div class="empty-state">
                <svg class="empty-icon"><use href="#icon-shopping-bag"></use></svg>
                <div class="empty-title">Belum ada pesanan</div>
                <div class="empty-sub">Pesanan dari pelanggan akan muncul di sini.</div>
              </div>
            </td>
          </tr>
        `;
        return;
      }

      tbody.innerHTML = filtered.map(o => `
        <tr>
          <td><strong style="color: var(--admin-accent);">${o.id}</strong></td>
          <td>
            <strong>${o.recipient}</strong>
            <div style="font-size: 11px; color: var(--admin-text-muted);">${o.city} (${o.courier})</div>
          </td>
          <td>${o.itemCount} item</td>
          <td><strong style="color: var(--admin-accent);">${formatRupiah(o.total)}</strong></td>
          <td>${o.paymentMethod}</td>
          <td><span class="status-pill ${getPaymentStatusClass(o.paymentStatus)}">${o.paymentStatus}</span></td>
          <td>
            <span class="status-pill ${getOrderStatusClass(o.orderStatus)}">${o.orderStatus}</span>
            ${o.trackingNumber ? `<div style="font-size: 10px; color: var(--admin-text-muted); margin-top: 2px;">Resi: ${o.trackingNumber}</div>` : ''}
          </td>
          <td>${o.date}</td>
          <td>
            <a href="#pesanan-detail" class="btn btn-xs btn-soft" onclick="window.activeOrderCode='${o.id}'">Lihat</a>
          </td>
        </tr>
      `).join('');
    }

    // Tab Klik
    document.querySelectorAll('.tab-pill').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-pill').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        activeTab = btn.dataset.tab;
        renderOrderRows();
      });
    });

    document.getElementById('searchOrder').addEventListener('input', renderOrderRows);
    document.getElementById('filterPayment').addEventListener('change', renderOrderRows);
    document.getElementById('filterShipping').addEventListener('change', renderOrderRows);
    renderOrderRows();

    initMobileDrawer();
  }

  // -------------------------------------------------------------------------
  // 8.7 Detail Pesanan (Pola Detail 8+4)
  // -------------------------------------------------------------------------
  function renderPesananDetail(header, content) {
    const orderCode = window.activeOrderCode || 'RK-0014';
    const order = state.orders.find(o => o.id === orderCode) || state.orders[0];

    header.innerHTML = `
      <div class="admin-header-row-1">
        <button class="admin-hamburger" id="btnHamburger" aria-label="Buka menu navigasi">
          <svg class="icon icon-md"><use href="#icon-menu"></use></svg>
        </button>
        <div class="admin-breadcrumb">
          <a href="#ringkasan">Admin</a>
          <span class="admin-breadcrumb-sep">></span>
          <a href="#pesanan">Pesanan</a>
          <span class="admin-breadcrumb-sep">></span>
          <span>${order.id}</span>
        </div>
      </div>
      <div class="admin-header-row-2">
        <div class="admin-title-area">
          <h1 class="admin-title">Pesanan ${order.id}</h1>
          <div class="admin-subtitle">Dibuat pada ${order.date} • ${order.recipient}</div>
        </div>
        <div class="admin-actions-area">
          <a href="#pesanan" class="btn btn-secondary">Kembali ke Daftar</a>
        </div>
      </div>
    `;

    // Hitung posisi progress bar
    const steps = ['Menunggu Pembayaran', 'Dibayar', 'Diproses', 'Dikirim', 'Selesai'];
    const currentStepIndex = steps.indexOf(order.orderStatus);

    content.innerHTML = `
      <div class="grid-12">
        <!-- Kolom Utama (8 Kolom) -->
        <div class="col-8">
          <!-- Kartu Item Pesanan -->
          <div class="admin-card">
            <div class="admin-card-header">
              <div class="admin-card-title">Item Pesanan</div>
            </div>

            <div class="table-responsive">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Produk & Varian</th>
                    <th>Jenis</th>
                    <th>Jumlah</th>
                    <th>Harga Satuan</th>
                    <th>Subtotal</th>
                  </tr>
                </thead>
                <tbody>
                  ${order.items.map(item => `
                    <tr>
                      <td>
                        <strong>${item.product}</strong>
                        <div style="font-size: 11px; color: var(--admin-text-muted);">${item.variant}</div>
                        ${item.isPreOrder ? '<span class="status-pill status-pre-order" style="margin-top: 4px;">Pre-Order</span>' : ''}
                      </td>
                      <td>${item.type}</td>
                      <td>${item.qty} pcs</td>
                      <td>${formatRupiah(item.price)}</td>
                      <td><strong>${formatRupiah(item.subtotal)}</strong></td>
                    </tr>
                  `).join('')}
                </tbody>
              </table>
            </div>

            <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--admin-border); display: flex; flex-direction: column; align-items: flex-end; gap: 6px;">
              <div style="font-size: 13px; color: var(--admin-text-muted);">
                Subtotal Produk: <strong style="color: var(--admin-text);">${formatRupiah(order.subtotal)}</strong>
              </div>
              <div style="font-size: 13px; color: var(--admin-text-muted);">
                Ongkos Kirim (${order.courier}): <strong style="color: var(--admin-text);">${formatRupiah(order.shippingFee)}</strong>
              </div>
              <div style="font-size: 16px; font-weight: 600; color: var(--admin-accent); margin-top: 4px;">
                Total Bayar: <span style="font-size: 26px; font-weight: 700;">${formatRupiah(order.total)}</span>
              </div>
            </div>
          </div>

          <!-- Kartu Penerima & Pengiriman -->
          <div class="admin-card">
            <div class="admin-card-header">
              <div class="admin-card-title">Penerima dan Pengiriman</div>
            </div>

            <div class="grid-12">
              <div class="col-6">
                <div class="form-group">
                  <div class="form-label">Nama Penerima</div>
                  <div style="font-size: 14px; font-weight: 600;">${order.recipient}</div>
                </div>
                <div class="form-group">
                  <div class="form-label">Nomor WhatsApp</div>
                  <div style="display: flex; align-items: center; gap: 8px;">
                    <span>${order.phone}</span>
                    <a href="https://wa.me/${order.phone}" target="_blank" class="btn btn-xs btn-soft-success">
                      <svg class="icon icon-sm"><use href="#icon-message-circle"></use></svg>
                      Hubungi
                    </a>
                  </div>
                </div>
              </div>

              <div class="col-6">
                <div class="form-group">
                  <div class="form-label">Ekspedisi & Layanan</div>
                  <div><strong>${order.courier}</strong> - ${order.service} (Estimasi: ${order.estimate})</div>
                </div>
                <div class="form-group">
                  <div class="form-label">Nomor Resi</div>
                  <div>${order.trackingNumber ? `<strong>${order.trackingNumber}</strong>` : '<span style="color: var(--admin-text-muted);">Belum ada nomor resi</span>'}</div>
                </div>
              </div>
            </div>

            <div class="form-group" style="margin-top: 8px; padding-top: 12px; border-top: 1px solid var(--admin-border);">
              <div class="form-label">Alamat Lengkap Tujuan</div>
              <div style="line-height: 1.5;">${order.address}, Kota ${order.city}</div>
            </div>
          </div>
        </div>

        <!-- Kolom Samping (4 Kolom) -->
        <div class="col-4">
          <!-- Kartu Status Pesanan & Bar Progres -->
          <div class="admin-card">
            <div class="admin-card-header">
              <div class="admin-card-title">Status Pesanan</div>
              <span class="status-pill ${getOrderStatusClass(order.orderStatus)}">${order.orderStatus}</span>
            </div>

            ${order.orderStatus !== 'Dibatalkan' ? `
              <div class="order-progress-bar">
                <div class="progress-line">
                  <div class="progress-line-fill" style="width: ${Math.max(0, currentStepIndex) * 25}%;"></div>
                </div>
                ${steps.map((st, i) => {
                  let cls = '';
                  if (i < currentStepIndex) cls = 'completed';
                  else if (i === currentStepIndex) cls = 'current';
                  return `
                    <div class="progress-step ${cls}">
                      <div class="progress-circle">
                        ${i < currentStepIndex ? '<svg class="icon icon-sm"><use href="#icon-check"></use></svg>' : i + 1}
                      </div>
                      <div class="progress-label">${st}</div>
                    </div>
                  `;
                }).join('')}
              </div>

              <form id="formUpdateOrderStatus" style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--admin-border);">
                <div class="form-group">
                  <label class="form-label">Ubah Status Pesanan</label>
                  <select id="selectNewStatus" class="form-select">
                    ${steps.map(st => `<option value="${st}" ${st === order.orderStatus ? 'selected' : ''}>${st}</option>`).join('')}
                    <option value="Dibatalkan">Dibatalkan</option>
                  </select>
                </div>

                <div id="resiFieldGroup" class="form-group" style="display: ${(order.orderStatus === 'Dikirim' && (order.courier === 'JNE' || order.courier === 'J&T')) ? 'block' : 'none'};">
                  <label class="form-label">Nomor Resi Pengiriman <span class="required">*</span></label>
                  <input type="text" id="inputNewResi" class="form-input" value="${order.trackingNumber}" placeholder="Masukkan nomor resi ekspedisi">
                </div>

                <button type="submit" class="btn btn-sm btn-primary" style="width: 100%; justify-content: center;">
                  Simpan Status
                </button>
              </form>
            ` : `
              <div class="form-warning">Pesanan ini telah Dibatalkan. Status tidak dapat diubah kembali.</div>
            `}
          </div>

          <!-- Kartu Pembayaran -->
          <div class="admin-card">
            <div class="admin-card-header">
              <div class="admin-card-title">Informasi Pembayaran</div>
              <span class="status-pill ${getPaymentStatusClass(order.paymentStatus)}">${order.paymentStatus}</span>
            </div>

            <div class="form-group">
              <div class="form-label">Metode Pembayaran</div>
              <div><strong>${order.paymentMethod}</strong></div>
            </div>

            <div class="form-group">
              <div class="form-label">Batas Waktu Bayar</div>
              <div>${order.paymentDeadline}</div>
            </div>

            <div class="form-group">
              <div class="form-label">Waktu Dibayar</div>
              <div>${order.paidAt}</div>
            </div>

            <!-- Tombol Konfirmasi Uang Tunai COD -->
            ${(order.paymentMethod.includes('COD') && order.paymentStatus !== 'Lunas') ? `
              <div style="margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--admin-border);">
                <button type="button" id="btnConfirmCOD" class="btn btn-sm btn-primary" style="width: 100%; justify-content: center;">
                  <svg class="icon icon-sm"><use href="#icon-banknote"></use></svg>
                  Uang Tunai Diterima
                </button>
              </div>
            ` : ''}
          </div>
        </div>
      </div>
    `;

    // Event Resi muncul saat Dikirim & Ekspedisi JNE/J&T
    const selectStatus = document.getElementById('selectNewStatus');
    const resiGroup = document.getElementById('resiFieldGroup');
    if (selectStatus && resiGroup) {
      selectStatus.addEventListener('change', () => {
        if (selectStatus.value === 'Dikirim' && (order.courier === 'JNE' || order.courier === 'J&T')) {
          resiGroup.style.display = 'block';
        } else {
          resiGroup.style.display = 'none';
        }
      });
    }

    // Submit Status
    const formStatus = document.getElementById('formUpdateOrderStatus');
    if (formStatus) {
      formStatus.addEventListener('submit', (e) => {
        e.preventDefault();
        const newStatus = selectStatus.value;
        const resiVal = document.getElementById('inputNewResi')?.value.trim();

        order.orderStatus = newStatus;
        if (newStatus === 'Dikirim' && resiVal) {
          order.trackingNumber = resiVal;
        }

        showToast(`Status pesanan ${order.id} berhasil diperbarui menjadi ${newStatus}.`, 'success');
        renderPesananDetail(header, content);
      });
    }

    // Tombol COD Uang Tunai Diterima
    const btnCOD = document.getElementById('btnConfirmCOD');
    if (btnCOD) {
      btnCOD.addEventListener('click', () => {
        showConfirmDialog({
          iconClass: 'modal-icon-primary',
          iconSymbol: 'icon-banknote',
          title: 'Konfirmasi Uang Tunai COD',
          descHtml: `Konfirmasi uang tunai <strong>${formatRupiah(order.total)}</strong> untuk pesanan <strong>${order.id}</strong> sudah diterima?`,
          confirmText: 'Konfirmasi Lunas',
          confirmClass: 'btn-primary',
          onConfirm: () => {
            order.paymentStatus = 'Lunas';
            order.paidAt = '6 Nov 2026 ' + new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
            showToast(`Uang tunai pesanan ${order.id} berhasil dikonfirmasi. Status pembayaran lunas.`, 'success');
            renderPesananDetail(header, content);
          }
        });
      });
    }

    initMobileDrawer();
  }

  // -------------------------------------------------------------------------
  // 8.8 Barang Masuk (Stok & Restock)
  // -------------------------------------------------------------------------
  function renderBarangMasuk(header, content) {
    header.innerHTML = `
      <div class="admin-header-row-1">
        <button class="admin-hamburger" id="btnHamburger" aria-label="Buka menu navigasi">
          <svg class="icon icon-md"><use href="#icon-menu"></use></svg>
        </button>
        <div class="admin-breadcrumb">
          <span>Admin</span>
          <span class="admin-breadcrumb-sep">></span>
          <span>Stok dan Keuangan</span>
          <span class="admin-breadcrumb-sep">></span>
          <span>Barang Masuk</span>
        </div>
      </div>
      <div class="admin-header-row-2">
        <div class="admin-title-area">
          <h1 class="admin-title">Barang Masuk</h1>
          <div class="admin-subtitle">Catat penambahan stok dari grosir dan pantau varian menipis.</div>
        </div>
      </div>
    `;

    // Buat daftar semua varian
    const allVariants = [];
    state.products.forEach(p => {
      p.variants.forEach(v => {
        allVariants.push({
          productId: p.id,
          productName: p.name,
          variantName: v.name,
          fullName: `${p.name} - ${v.name}`,
          stock: v.stock,
          modalPrice: p.modalPrice
        });
      });
    });

    content.innerHTML = `
      <div class="grid-12">
        <!-- Kolom Utama (8 Kolom) -->
        <div class="col-8">
          <!-- Form Catat Barang Masuk -->
          <div class="admin-card">
            <div class="admin-card-header">
              <div class="admin-card-title">Catat Barang Masuk</div>
            </div>

            <form id="formIncomingStock">
              <div class="form-group">
                <label class="form-label">Varian Produk <span class="required">*</span></label>
                <select id="incomingVariantSelect" class="form-select" required>
                  <option value="">-- Pilih Varian Produk --</option>
                  ${allVariants.map(v => `
                    <option value="${v.fullName}" data-stock="${v.stock}" data-modal="${v.modalPrice}">
                      ${v.fullName} (Stok: ${v.stock} pcs)
                    </option>
                  `).join('')}
                </select>
                <div id="incomingStockHelper" class="form-help" style="color: var(--admin-accent); font-weight: 500;">
                  Pilih varian untuk melihat sisa stok dan batas minimum.
                </div>
              </div>

              <div class="grid-12">
                <div class="col-6">
                  <div class="form-group">
                    <label class="form-label">Nama Grosir (Opsional)</label>
                    <input type="text" id="incomingWholesaler" class="form-input" placeholder="Contoh: Grosir Kosmetik Surabaya">
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-group">
                    <label class="form-label">Jumlah Masuk (pcs) <span class="required">*</span></label>
                    <input type="number" id="incomingQty" class="form-input" value="12" min="1" required>
                  </div>
                </div>
              </div>

              <div class="grid-12">
                <div class="col-6">
                  <div class="form-group">
                    <label class="form-label">Harga Modal Satuan <span class="required">*</span></label>
                    <div class="input-rupiah-wrap">
                      <span class="prefix">Rp</span>
                      <input type="number" id="incomingModalPrice" class="form-input" value="28000" required>
                    </div>
                    <div class="form-help">Memperbarui harga modal produk.</div>
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-group">
                    <label class="form-label">Tanggal Masuk <span class="required">*</span></label>
                    <input type="date" id="incomingDate" class="form-input" value="2026-11-06" required>
                  </div>
                </div>
              </div>

              <div style="margin-top: 16px;">
                <button type="submit" class="btn btn-primary">
                  Simpan Barang Masuk
                </button>
              </div>
            </form>
          </div>

          <!-- Riwayat Barang Masuk -->
          <div class="admin-card">
            <div class="admin-card-header">
              <div class="admin-card-title">Riwayat Barang Masuk</div>
            </div>

            <div class="table-responsive">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Tanggal</th>
                    <th>Produk & Varian</th>
                    <th>Grosir</th>
                    <th>Jumlah</th>
                    <th>Harga Modal</th>
                    <th>Dicatat Oleh</th>
                  </tr>
                </thead>
                <tbody id="incomingHistoryBody">
                  ${state.incomingStock.map(item => `
                    <tr>
                      <td>${item.date}</td>
                      <td><strong>${item.product}</strong></td>
                      <td>${item.wholesaler || '-'}</td>
                      <td><strong>+${item.qty} pcs</strong></td>
                      <td>${formatRupiah(item.modalPrice)}</td>
                      <td>${item.recordedBy}</td>
                    </tr>
                  `).join('')}
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Kolom Samping (4 Kolom): Segera Restock -->
        <div class="col-4">
          <div class="admin-card">
            <div class="admin-card-header">
              <div class="admin-card-title">Segera Restock</div>
              <span class="status-pill status-segera-restock">3 varian</span>
            </div>
            <div class="admin-card-desc" style="margin-bottom: 12px;">Klik baris untuk mengisi form barang masuk otomatis.</div>

            <div style="display: flex; flex-direction: column; gap: 8px;">
              <div class="admin-card admin-card-clickable" style="padding: 12px; margin-bottom: 0;" onclick="window.selectRestockItem('Gentle Sunscreen Gel 50ml - Standar 50ml', 38000)">
                <div style="font-weight: 600;">Gentle Sunscreen Gel 50ml</div>
                <div style="font-size: 11px; color: var(--admin-text-muted);">Standar 50ml</div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 6px;">
                  <span class="status-pill status-stok-habis">Stok: 0 pcs</span>
                  <span style="font-size: 11px; color: var(--admin-text-muted);">Batas min: 5 pcs</span>
                </div>
              </div>

              <div class="admin-card admin-card-clickable" style="padding: 12px; margin-bottom: 0;" onclick="window.selectRestockItem('Dewy Cushion SPF 35 - 02 Warm Beige', 72000)">
                <div style="font-weight: 600;">Dewy Cushion SPF 35</div>
                <div style="font-size: 11px; color: var(--admin-text-muted);">02 Warm Beige</div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 6px;">
                  <span class="status-pill status-segera-restock">Stok: 6 pcs</span>
                  <span style="font-size: 11px; color: var(--admin-text-muted);">Batas min: 10 pcs</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    `;

    // Helper teks saat varian dipilih
    const varSelect = document.getElementById('incomingVariantSelect');
    const helper = document.getElementById('incomingStockHelper');
    const modalInput = document.getElementById('incomingModalPrice');

    varSelect.addEventListener('change', () => {
      const selectedOpt = varSelect.options[varSelect.selectedIndex];
      if (varSelect.value) {
        const curStock = selectedOpt.dataset.stock;
        const curModal = selectedOpt.dataset.modal;
        helper.textContent = `Stok sekarang ${curStock} pcs, batas minimum ${state.storeSettings.minStockThreshold} pcs.`;
        if (curModal) modalInput.value = curModal;
      } else {
        helper.textContent = 'Pilih varian untuk melihat sisa stok dan batas minimum.';
      }
    });

    window.selectRestockItem = function (fullName, modal) {
      varSelect.value = fullName;
      modalInput.value = modal;
      varSelect.dispatchEvent(new Event('change'));
      document.getElementById('incomingQty').focus();
    };

    // Form Submit
    document.getElementById('formIncomingStock').addEventListener('submit', (e) => {
      e.preventDefault();
      const variantName = varSelect.value;
      const wholesaler = document.getElementById('incomingWholesaler').value.trim();
      const qty = parseInt(document.getElementById('incomingQty').value) || 0;
      const modal = parseFloat(modalInput.value) || 0;

      // Update stok pada state
      let updatedTotal = 0;
      state.products.forEach(p => {
        p.variants.forEach(v => {
          if (`${p.name} - ${v.name}` === variantName) {
            v.stock += qty;
            p.modalPrice = modal;
            updatedTotal = v.stock;
          }
        });
      });

      state.incomingStock.unshift({
        id: Date.now(),
        date: '6 Nov 2026',
        product: variantName,
        wholesaler,
        qty,
        modalPrice: modal,
        recordedBy: 'Ariel'
      });

      // Notifikasi Toast sesuai spesifikasi Section 5.6
      showToast(`Barang masuk untuk ${variantName} sebanyak ${qty} pcs berhasil dicatat.`, 'success');

      setTimeout(() => {
        if (updatedTotal < state.storeSettings.minStockThreshold) {
          showToast(`Stok varian masih di bawah batas minimum (${updatedTotal} pcs), varian tetap ada di daftar Segera Restock.`, 'info');
        } else {
          showToast(`Stok varian sekarang cukup (${updatedTotal} pcs), varian tidak masuk daftar Segera Restock.`, 'info');
        }
      }, 700);

      renderBarangMasuk(header, content);
    });

    initMobileDrawer();
  }

  // -------------------------------------------------------------------------
  // 8.9 Penjualan Langsung (Door-to-door)
  // -------------------------------------------------------------------------
  function renderPenjualanLangsung(header, content) {
    header.innerHTML = `
      <div class="admin-header-row-1">
        <button class="admin-hamburger" id="btnHamburger" aria-label="Buka menu navigasi">
          <svg class="icon icon-md"><use href="#icon-menu"></use></svg>
        </button>
        <div class="admin-breadcrumb">
          <span>Admin</span>
          <span class="admin-breadcrumb-sep">></span>
          <span>Stok dan Keuangan</span>
          <span class="admin-breadcrumb-sep">></span>
          <span>Penjualan Langsung</span>
        </div>
      </div>
      <div class="admin-header-row-2">
        <div class="admin-title-area">
          <h1 class="admin-title">Penjualan Langsung</h1>
          <div class="admin-subtitle">Catat dan tinjau transaksi offline door-to-door langsung ke pelanggan.</div>
        </div>
        <div class="admin-actions-area">
          <button id="btnOpenNewSaleForm" class="btn btn-primary">
            <svg class="icon icon-sm"><use href="#icon-plus"></use></svg>
            Catat Penjualan
          </button>
        </div>
      </div>
    `;

    content.innerHTML = `
      <!-- Form Input Penjualan Langsung (Tersembunyi by default atau toggle) -->
      <div id="newSaleCard" class="admin-card" style="display: none; border-color: var(--admin-primary); margin-bottom: 24px;">
        <div class="admin-card-header">
          <div class="admin-card-title">Catat Penjualan Baru</div>
          <button type="button" class="btn btn-xs btn-ghost" onclick="document.getElementById('newSaleCard').style.display='none'">
            <svg class="icon icon-sm"><use href="#icon-x"></use></svg>
          </button>
        </div>

        <form id="formDirectSale">
          <div class="grid-12">
            <div class="col-8">
              <div class="grid-12">
                <div class="col-6">
                  <div class="form-group">
                    <label class="form-label">Tanggal Penjualan <span class="required">*</span></label>
                    <input type="date" id="saleDate" class="form-input" value="2026-11-06" required>
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-group">
                    <label class="form-label">Nama Pelanggan (Opsional)</label>
                    <input type="text" id="saleCustomer" class="form-input" placeholder="Contoh: Ibu Rina (Tetangga)">
                  </div>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label">Catatan Transaksi (Opsional)</label>
                <input type="text" id="saleNote" class="form-input" placeholder="Contoh: Pembayaran tunai lunas">
              </div>

              <!-- Baris Item Produk -->
              <div class="form-group" style="margin-top: 16px;">
                <label class="form-label">Item Produk</label>
                <div class="repeater-list" id="saleItemsList">
                  <div class="repeater-row sale-item-row">
                    <select class="form-select sale-var-select" style="flex: 2;" required>
                      <option value="">-- Pilih Produk --</option>
                      ${state.products.map(p => `
                        <option value="${p.id}" data-retail="${p.retailPrice}" data-pack="${p.packPricePerPcs || p.retailPrice}" data-stock="${p.variants[0]?.stock || 0}">
                          ${p.name} (Stok: ${p.variants.reduce((a, b) => a + b.stock, 0)} pcs)
                        </option>
                      `).join('')}
                    </select>

                    <div class="segmented-control" style="flex-shrink: 0;">
                      <button type="button" class="segment-btn active" data-type="Eceran">Eceran</button>
                      <button type="button" class="segment-btn" data-type="Pack">Pack</button>
                    </div>

                    <input type="number" class="form-input sale-qty" value="1" min="1" style="width: 70px;" required>
                    <input type="text" class="form-input sale-subtotal" value="Rp0" style="width: 100px; text-align: right; background-color: #F6EAE8;" readonly>

                    <button type="button" class="btn btn-xs btn-ghost btn-del-sale-item" style="color: var(--admin-danger);">
                      <svg class="icon icon-sm"><use href="#icon-trash-2"></use></svg>
                    </button>
                  </div>
                </div>

                <button type="button" id="btnAddSaleItem" class="btn btn-sm btn-secondary" style="margin-top: 10px;">
                  <svg class="icon icon-sm"><use href="#icon-plus"></use></svg>
                  Tambah Item
                </button>
              </div>
            </div>

            <!-- Kolom Ringkasan -->
            <div class="col-4">
              <div style="background-color: var(--admin-bg); border-radius: var(--admin-radius-card); padding: 20px; border: 1px solid var(--admin-border); height: 100%; display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                  <div class="admin-card-title">Ringkasan</div>
                  <div style="margin-top: 16px; font-size: 13px; color: var(--admin-text-muted);">
                    Jumlah Item: <strong id="saleTotalItems" style="color: var(--admin-text);">1 pcs</strong>
                  </div>
                  <div style="margin-top: 8px; font-size: 13px; color: var(--admin-text-muted);">
                    Total Penjualan:
                    <div id="saleTotalAmount" style="font-size: 28px; font-weight: 700; color: var(--admin-accent); margin-top: 4px;">Rp0</div>
                  </div>
                </div>

                <div style="margin-top: 24px;">
                  <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                    Simpan Penjualan
                  </button>
                </div>
              </div>
            </div>
          </div>
        </form>
      </div>

      <!-- Tabel Daftar Penjualan Langsung -->
      <div class="admin-card">
        <div class="admin-card-header">
          <div class="admin-card-title-wrap">
            <span class="admin-card-title">Riwayat Penjualan Langsung</span>
            <span class="status-pill status-dibatalkan">${state.directSales.length} transaksi</span>
          </div>
        </div>

        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Tanggal Penjualan</th>
                <th>Nama Pelanggan</th>
                <th>Jumlah Item</th>
                <th>Total</th>
                <th>Catatan</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              ${state.directSales.map(s => `
                <tr>
                  <td>${s.date}</td>
                  <td><strong>${s.customer || '-'}</strong></td>
                  <td>${s.itemCount} item</td>
                  <td><strong style="color: var(--admin-accent);">${formatRupiah(s.total)}</strong></td>
                  <td>${s.note}</td>
                  <td>
                    <button class="btn btn-xs btn-soft">Lihat</button>
                  </td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      </div>
    `;

    // Handler Buka Form
    document.getElementById('btnOpenNewSaleForm').addEventListener('click', () => {
      document.getElementById('newSaleCard').style.display = 'block';
      window.scrollTo({ top: 120, behavior: 'smooth' });
    });

    // Kalkulasi Harga Baris & Total
    function updateSaleTotals() {
      let grandTotal = 0;
      let totalQty = 0;

      document.querySelectorAll('.sale-item-row').forEach(row => {
        const select = row.querySelector('.sale-var-select');
        const opt = select.options[select.selectedIndex];
        const qty = parseInt(row.querySelector('.sale-qty').value) || 0;
        const activeType = row.querySelector('.segment-btn.active').dataset.type;

        let unitPrice = 0;
        if (opt && opt.value) {
          unitPrice = activeType === 'Pack' ? parseFloat(opt.dataset.pack) : parseFloat(opt.dataset.retail);
        }

        const subtotal = unitPrice * qty;
        row.querySelector('.sale-subtotal').value = formatRupiah(subtotal);

        grandTotal += subtotal;
        totalQty += qty;
      });

      document.getElementById('saleTotalItems').textContent = `${totalQty} pcs`;
      document.getElementById('saleTotalAmount').textContent = formatRupiah(grandTotal);
    }

    function attachSaleItemEvents(row) {
      row.querySelectorAll('.segment-btn').forEach(btn => {
        btn.onclick = () => {
          row.querySelectorAll('.segment-btn').forEach(b => b.classList.remove('active'));
          btn.classList.add('active');
          updateSaleTotals();
        };
      });

      row.querySelector('.sale-var-select').addEventListener('change', updateSaleTotals);
      row.querySelector('.sale-qty').addEventListener('input', () => {
        const select = row.querySelector('.sale-var-select');
        const opt = select.options[select.selectedIndex];
        const maxStock = parseInt(opt?.dataset.stock) || 999;
        const val = parseInt(row.querySelector('.sale-qty').value) || 0;

        if (val > maxStock) {
          showToast(`Stok varian tinggal ${maxStock} pcs.`, 'danger');
          row.querySelector('.sale-qty').value = maxStock;
        }
        updateSaleTotals();
      });

      row.querySelector('.btn-del-sale-item').onclick = () => {
        if (document.querySelectorAll('.sale-item-row').length > 1) {
          row.remove();
          updateSaleTotals();
        } else {
          showToast('Minimal harus ada 1 item.', 'danger');
        }
      };
    }

    document.querySelectorAll('.sale-item-row').forEach(attachSaleItemEvents);

    document.getElementById('btnAddSaleItem').addEventListener('click', () => {
      const list = document.getElementById('saleItemsList');
      const newRow = document.createElement('div');
      newRow.className = 'repeater-row sale-item-row';
      newRow.innerHTML = `
        <select class="form-select sale-var-select" style="flex: 2;" required>
          <option value="">-- Pilih Produk --</option>
          ${state.products.map(p => `
            <option value="${p.id}" data-retail="${p.retailPrice}" data-pack="${p.packPricePerPcs || p.retailPrice}" data-stock="${p.variants[0]?.stock || 0}">
              ${p.name} (Stok: ${p.variants.reduce((a, b) => a + b.stock, 0)} pcs)
            </option>
          `).join('')}
        </select>
        <div class="segmented-control" style="flex-shrink: 0;">
          <button type="button" class="segment-btn active" data-type="Eceran">Eceran</button>
          <button type="button" class="segment-btn" data-type="Pack">Pack</button>
        </div>
        <input type="number" class="form-input sale-qty" value="1" min="1" style="width: 70px;" required>
        <input type="text" class="form-input sale-subtotal" value="Rp0" style="width: 100px; text-align: right; background-color: #F6EAE8;" readonly>
        <button type="button" class="btn btn-xs btn-ghost btn-del-sale-item" style="color: var(--admin-danger);">
          <svg class="icon icon-sm"><use href="#icon-trash-2"></use></svg>
        </button>
      `;
      list.appendChild(newRow);
      attachSaleItemEvents(newRow);
    });

    document.getElementById('formDirectSale').addEventListener('submit', (e) => {
      e.preventDefault();
      const customer = document.getElementById('saleCustomer').value.trim() || 'Pelanggan Langsung';
      const note = document.getElementById('saleNote').value.trim() || 'Penjualan tunai';
      const grandTotalStr = document.getElementById('saleTotalAmount').textContent;
      const grandTotal = parseInt(grandTotalStr.replace(/[^0-9]/g, '')) || 0;
      const totalItems = parseInt(document.getElementById('saleTotalItems').textContent) || 1;

      state.directSales.unshift({
        id: Date.now(),
        date: '6 Nov 2026',
        customer,
        itemCount: totalItems,
        total: grandTotal,
        note
      });

      showToast(`Penjualan langsung sebesar ${formatRupiah(grandTotal)} berhasil disimpan.`, 'success');
      renderPenjualanLangsung(header, content);
    });

    initMobileDrawer();
  }

  // -------------------------------------------------------------------------
  // 8.10 Rekap Bulanan
  // -------------------------------------------------------------------------
  function renderRekapBulanan(header, content) {
    header.innerHTML = `
      <div class="admin-header-row-1">
        <button class="admin-hamburger" id="btnHamburger" aria-label="Buka menu navigasi">
          <svg class="icon icon-md"><use href="#icon-menu"></use></svg>
        </button>
        <div class="admin-breadcrumb">
          <span>Admin</span>
          <span class="admin-breadcrumb-sep">></span>
          <span>Stok dan Keuangan</span>
          <span class="admin-breadcrumb-sep">></span>
          <span>Rekap Bulanan</span>
        </div>
      </div>
      <div class="admin-header-row-2">
        <div class="admin-title-area">
          <h1 class="admin-title">Rekap Bulanan</h1>
          <div class="admin-subtitle">Laporan penjualan, estimasi laba kotor, dan produk terlaris.</div>
        </div>
        <div class="admin-actions-area">
          <!-- Kontrol Bulan -->
          <div style="display: flex; align-items: center; background-color: #FFFFFF; border: 1px solid var(--admin-border); border-radius: var(--admin-radius-pill); padding: 2px 8px;">
            <button class="btn btn-xs btn-ghost" title="Bulan sebelumnya">
              <svg class="icon icon-sm"><use href="#icon-chevron-left"></use></svg>
            </button>
            <span style="font-weight: 600; padding: 0 8px;">Nov 2026</span>
            <button class="btn btn-xs btn-ghost" title="Bulan berikutnya">
              <svg class="icon icon-sm"><use href="#icon-chevron-right"></use></svg>
            </button>
          </div>

          <!-- Tombol Cetak / Unduh PDF -->
          <button id="btnPrintReport" class="btn btn-secondary">
            <svg class="icon icon-sm"><use href="#icon-download"></use></svg>
            Unduh PDF / Cetak
          </button>
        </div>
      </div>
    `;

    // Data Metrik Rekap
    const onlineSales = 1250000;
    const directSales = 420000;
    const incomingModal = 890000;
    const grossProfit = 780000;

    content.innerHTML = `
      <!-- Kop Surat Khusus Tampilan Cetak / PDF -->
      <div class="print-letterhead">
        <h2 style="font-size: 20px; font-weight: 700; color: #4A2C35;">RiasKu Official Store - Laporan Rekap Bulanan</h2>
        <div style="font-size: 13px; color: #8A6F76;">Periode: November 2026 • Dicetak pada: 6 Nov 2026</div>
      </div>

      <!-- 4 Kartu Metrik Rekap -->
      <div class="metric-grid-4">
        <div class="metric-card">
          <div class="metric-top"><span class="metric-label">Penjualan online</span></div>
          <div class="metric-value is-currency">${formatRupiah(onlineSales)}</div>
          <div class="metric-help">Dari 18 pesanan selesai</div>
        </div>

        <div class="metric-card">
          <div class="metric-top"><span class="metric-label">Penjualan langsung</span></div>
          <div class="metric-value is-currency">${formatRupiah(directSales)}</div>
          <div class="metric-help">Dari 12 transaksi door-to-door</div>
        </div>

        <div class="metric-card">
          <div class="metric-top"><span class="metric-label">Barang masuk</span></div>
          <div class="metric-value is-currency">${formatRupiah(incomingModal)}</div>
          <div class="metric-help">Total modal belanja grosir</div>
        </div>

        <div class="metric-card">
          <div class="metric-top"><span class="metric-label">Laba kotor</span></div>
          <div class="metric-value is-currency">${formatRupiah(grossProfit)}</div>
          <div class="metric-help">Harga jual dikurangi modal tersimpan</div>
        </div>
      </div>

      <div class="grid-12">
        <!-- Kolom Kiri: Produk Terlaris (8 Kolom) -->
        <div class="col-8">
          <div class="admin-card">
            <div class="admin-card-header">
              <div class="admin-card-title">Produk Terlaris</div>
              <div class="admin-card-desc">Berdasarkan volume unit terjual bulan ini</div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 4px;">
              <div class="bar-chart-row">
                <div class="bar-rank top-3">1</div>
                <img src="RiasKu Serif R App Icon.png" alt="Produk" class="table-thumb" style="width: 38px; height: 38px;">
                <div class="bar-product-info">
                  <div class="bar-product-name">Glow Velvet Lip Tint (All Variants)</div>
                  <div class="bar-track">
                    <div class="bar-fill" style="width: 100%;"></div>
                  </div>
                </div>
                <div class="bar-stat">
                  <div class="bar-stat-qty">38 pcs</div>
                  <div class="bar-stat-rev">${formatRupiah(760000)}</div>
                </div>
              </div>

              <div class="bar-chart-row">
                <div class="bar-rank top-3">2</div>
                <img src="RiasKu Serif R App Icon.png" alt="Produk" class="table-thumb" style="width: 38px; height: 38px;">
                <div class="bar-product-info">
                  <div class="bar-product-name">Dewy Cushion SPF 35 PA+++</div>
                  <div class="bar-track">
                    <div class="bar-fill" style="width: 65%;"></div>
                  </div>
                </div>
                <div class="bar-stat">
                  <div class="bar-stat-qty">24 pcs</div>
                  <div class="bar-stat-rev">${formatRupiah(510000)}</div>
                </div>
              </div>

              <div class="bar-chart-row">
                <div class="bar-rank top-3">3</div>
                <img src="RiasKu Serif R App Icon.png" alt="Produk" class="table-thumb" style="width: 38px; height: 38px;">
                <div class="bar-product-info">
                  <div class="bar-product-name">Gentle Sunscreen Gel 50ml</div>
                  <div class="bar-track">
                    <div class="bar-fill" style="width: 42%;"></div>
                  </div>
                </div>
                <div class="bar-stat">
                  <div class="bar-stat-qty">16 pcs</div>
                  <div class="bar-stat-rev">${formatRupiah(245000)}</div>
                </div>
              </div>

              <div class="bar-chart-row">
                <div class="bar-rank">4</div>
                <img src="RiasKu Serif R App Icon.png" alt="Produk" class="table-thumb" style="width: 38px; height: 38px;">
                <div class="bar-product-info">
                  <div class="bar-product-name">Matte Powder Foundation</div>
                  <div class="bar-track">
                    <div class="bar-fill" style="width: 25%;"></div>
                  </div>
                </div>
                <div class="bar-stat">
                  <div class="bar-stat-qty">9 pcs</div>
                  <div class="bar-stat-rev">${formatRupiah(155000)}</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Kolom Kanan: Ringkasan Bulan (4 Kolom) -->
        <div class="col-4">
          <div class="admin-card">
            <div class="admin-card-header">
              <div class="admin-card-title">Ringkasan Bulan</div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 14px;">
              <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #F6EAE8; padding-bottom: 10px;">
                <span style="color: var(--admin-text-muted);">Jumlah pesanan online</span>
                <strong>18 pesanan</strong>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #F6EAE8; padding-bottom: 10px;">
                <span style="color: var(--admin-text-muted);">Jumlah penjualan langsung</span>
                <strong>12 transaksi</strong>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #F6EAE8; padding-bottom: 10px;">
                <span style="color: var(--admin-text-muted);">Total unit terjual</span>
                <strong style="color: var(--admin-accent); font-size: 15px;">87 pcs</strong>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #F6EAE8; padding-bottom: 10px;">
                <span style="color: var(--admin-text-muted);">Rata-rata order (AOV)</span>
                <strong>${formatRupiah(69440)}</strong>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="color: var(--admin-text-muted);">Margin keuntungan kotor</span>
                <strong style="color: var(--admin-success);">46.7%</strong>
              </div>
            </div>
          </div>
        </div>
      </div>
    `;

    document.getElementById('btnPrintReport').addEventListener('click', () => {
      window.print();
    });

    initMobileDrawer();
  }

  // -------------------------------------------------------------------------
  // 8.11 Pengaturan Toko (Pola Form 1 Kolom Maks 720px)
  // -------------------------------------------------------------------------
  function renderPengaturan(header, content) {
    header.innerHTML = `
      <div class="admin-header-row-1">
        <button class="admin-hamburger" id="btnHamburger" aria-label="Buka menu navigasi">
          <svg class="icon icon-md"><use href="#icon-menu"></use></svg>
        </button>
        <div class="admin-breadcrumb">
          <span>Admin</span>
          <span class="admin-breadcrumb-sep">></span>
          <span>Pengaturan Toko</span>
        </div>
      </div>
      <div class="admin-header-row-2">
        <div class="admin-title-area">
          <h1 class="admin-title">Pengaturan Toko</h1>
          <div class="admin-subtitle">Informasi profil toko, nomor WhatsApp, dan aturan operasional.</div>
        </div>
      </div>
    `;

    const settings = state.storeSettings;

    content.innerHTML = `
      <form id="settingsForm" style="max-width: 720px;">
        <!-- Kartu Info Toko -->
        <div class="admin-card">
          <div class="admin-card-header">
            <div class="admin-card-title">Info Toko</div>
          </div>

          <div class="form-group">
            <label class="form-label">Nama Toko <span class="required">*</span></label>
            <input type="text" id="settingStoreName" class="form-input" value="${settings.storeName}" required>
          </div>

          <div class="form-group">
            <label class="form-label">Nomor WhatsApp Admin <span class="required">*</span></label>
            <input type="text" id="settingWhatsapp" class="form-input" value="${settings.whatsapp}" required>
            <div class="form-help">Dipakai untuk tombol "Tanya via WhatsApp" dan notifikasi pelanggan.</div>
          </div>

          <div class="form-group">
            <label class="form-label">Alamat Asal Pengiriman <span class="required">*</span></label>
            <textarea id="settingAddress" class="form-textarea" required>${settings.originAddress}</textarea>
          </div>

          <div class="form-group">
            <label class="form-label">Kota Asal <span class="required">*</span></label>
            <input type="text" id="settingCity" class="form-input" value="${settings.originCity}" required>
            <div class="form-help">Dasar perhitungan ongkir ekspedisi JNE dan J&T.</div>
          </div>
        </div>

        <!-- Kartu Ongkir -->
        <div class="admin-card">
          <div class="admin-card-header">
            <div class="admin-card-title">Ongkos Kirim Lokal</div>
          </div>

          <div class="form-group">
            <label class="form-label">Ongkir Dalam Surabaya (Kurir Toko / Sameday)</label>
            <div class="input-rupiah-wrap">
              <span class="prefix">Rp</span>
              <input type="number" id="settingLocalShipping" class="form-input" value="${settings.localShippingFee}">
            </div>
            <div class="form-help">Masukkan Rp0 bila toko Anda memberikan gratis ongkir dalam kota.</div>
          </div>
        </div>

        <!-- Kartu Aturan Operasional -->
        <div class="admin-card">
          <div class="admin-card-header">
            <div class="admin-card-title">Aturan Operasional</div>
          </div>

          <div class="grid-12">
            <div class="col-6">
              <div class="form-group">
                <label class="form-label">Batas Stok Minimum (pcs) <span class="required">*</span></label>
                <input type="number" id="settingMinStock" class="form-input" value="${settings.minStockThreshold}" min="1" required>
                <div class="form-help">Varian di bawah angka ini otomatis masuk daftar Segera Restock.</div>
              </div>
            </div>

            <div class="col-6">
              <div class="form-group">
                <label class="form-label">Batas Waktu Bayar (Jam) <span class="required">*</span></label>
                <input type="number" id="settingPaymentDeadline" class="form-input" value="${settings.paymentDeadlineHours}" min="1" required>
                <div class="form-help">Pesanan online yang belum dibayar setelah batas ini dibatalkan otomatis.</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Bilah Aksi Simpan -->
        <div class="form-sticky-bar" style="max-width: 720px; border-radius: var(--admin-radius-card);">
          <button type="submit" class="btn btn-primary">Simpan Pengaturan</button>
        </div>
      </form>
    `;

    document.getElementById('settingsForm').addEventListener('submit', (e) => {
      e.preventDefault();
      settings.storeName = document.getElementById('settingStoreName').value.trim();
      settings.whatsapp = document.getElementById('settingWhatsapp').value.trim();
      settings.originAddress = document.getElementById('settingAddress').value.trim();
      settings.originCity = document.getElementById('settingCity').value.trim();
      settings.localShippingFee = parseFloat(document.getElementById('settingLocalShipping').value) || 0;
      settings.minStockThreshold = parseInt(document.getElementById('settingMinStock').value) || 5;
      settings.paymentDeadlineHours = parseInt(document.getElementById('settingPaymentDeadline').value) || 24;

      showToast('Pengaturan toko berhasil disimpan.', 'success');
    });

    initMobileDrawer();
  }

  // =========================================================================
  // 9. INISIALISASI APLIKASI
  // =========================================================================

  function initApp() {
    initFlyoutSystem();
    initMobileDrawer();

    window.addEventListener('hashchange', handleHashChange);
    handleHashChange();
  }

  // Tunggu DOM siap
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initApp);
  } else {
    initApp();
  }

})();

