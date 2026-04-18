---
layout: default
title: Walkthrough - Luma Network
---

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Getting Started - Luma Network</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: "Inter", sans-serif; }
        .step-card:hover { transform: translateY(-2px); }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <div class="max-w-5xl mx-auto px-4 py-12">
        <!-- Header -->
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">Getting Started with Luma Network</h1>
            <p class="text-lg text-gray-600">Panduan lengkap untuk setup hotspot WiFi bisnis Anda dalam 5 langkah mudah</p>
        </div>

        <!-- Progress Bar -->
        <div class="mb-12">
            <div class="flex justify-between items-center mb-2">
                <span class="text-sm font-medium text-gray-600">Progress</span>
                <span class="text-sm font-semibold text-indigo-600" id="progress-text">Step 1 of 5</span>
            </div>
            <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                <div id="progress-bar" class="h-full bg-indigo-600 rounded-full transition-all duration-500" style="width: 20%"></div>
            </div>
        </div>

        <!-- Steps -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
            
            <!-- Step 1 -->
            <div class="step-card bg-white rounded-2xl shadow-md p-6 border border-gray-100 transition-all duration-300 cursor-pointer hover:shadow-lg">
                <div class="flex items-center mb-4">
                    <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center mr-3">
                        <span class="text-indigo-600 font-bold">1</span>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">Daftar Akun</h3>
                </div>
                <p class="text-gray-600 text-sm mb-4">Buat akun baru untuk memulai. Gunakan email aktif untuk verifikasi.</p>
                <div class="space-y-2 text-sm">
                    <div class="flex items-center text-gray-500">
                        <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Kunjungi halaman registrasi
                    </div>
                    <div class="flex items-center text-gray-500">
                        <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Isi data venue & informasi bisnis
                    </div>
                    <div class="flex items-center text-gray-500">
                        <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Verifikasi email
                    </div>
                </div>
            </div>

            <!-- Step 2 -->
            <div class="step-card bg-white rounded-2xl shadow-md p-6 border border-gray-100 transition-all duration-300 cursor-pointer hover:shadow-lg">
                <div class="flex items-center mb-4">
                    <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center mr-3">
                        <span class="text-indigo-600 font-bold">2</span>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">Tambah Router</h3>
                </div>
                <p class="text-gray-600 text-sm mb-4">Hubungkan MikroTik router Anda ke sistem kami.</p>
                <div class="space-y-2 text-sm">
                    <div class="flex items-center text-gray-500">
                        <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Masukkan IP & credentials router
                    </div>
                    <div class="flex items-center text-gray-500">
                        <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Aktifkan hotspot di MikroTik
                    </div>
                    <div class="flex items-center text-gray-500">
                        <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Konfigurasi RADIUS
                    </div>
                </div>
            </div>

            <!-- Step 3 -->
            <div class="step-card bg-white rounded-2xl shadow-md p-6 border border-gray-100 transition-all duration-300 cursor-pointer hover:shadow-lg">
                <div class="flex items-center mb-4">
                    <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center mr-3">
                        <span class="text-indigo-600 font-bold">3</span>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">Pilih Login Method</h3>
                </div>
                <p class="text-gray-600 text-sm mb-4">Tentukan cara tamu login ke WiFi hotspot Anda.</p>
                <div class="space-y-2 text-sm">
                    <div class="flex items-center text-gray-500">
                        <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Google OAuth
                    </div>
                    <div class="flex items-center text-gray-500">
                        <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        WhatsApp OTP
                    </div>
                    <div class="flex items-center text-gray-500">
                        <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Input Nomor Kamar (untuk hotel)
                    </div>
                </div>
            </div>

            <!-- Step 4 -->
            <div class="step-card bg-white rounded-2xl shadow-md p-6 border border-gray-100 transition-all duration-300 cursor-pointer hover:shadow-lg">
                <div class="flex items-center mb-4">
                    <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center mr-3">
                        <span class="text-indigo-600 font-bold">4</span>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">Customize Branding</h3>
                </div>
                <p class="text-gray-600 text-sm mb-4">Atur tampilan captive portal sesuai brand bisnis Anda.</p>
                <div class="space-y-2 text-sm">
                    <div class="flex items-center text-gray-500">
                        <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Upload logo bisnis
                    </div>
                    <div class="flex items-center text-gray-500">
                        <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Set warna & tema
                    </div>
                    <div class="flex items-center text-gray-500">
                        <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Tambah kebijakan privasi
                    </div>
                </div>
            </div>

            <!-- Step 5 -->
            <div class="step-card bg-white rounded-2xl shadow-md p-6 border border-gray-100 transition-all duration-300 cursor-pointer hover:shadow-lg">
                <div class="flex items-center mb-4">
                    <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center mr-3">
                        <span class="text-indigo-600 font-bold">5</span>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">Testing & Go Live</h3>
                </div>
                <p class="text-gray-600 text-sm mb-4">Uji coba sistem dan mulai terima tamu.</p>
                <div class="space-y-2 text-sm">
                    <div class="flex items-center text-gray-500">
                        <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Test login dengan perangkat
                    </div>
                    <div class="flex items-center text-gray-500">
                        <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Cek dashboard analytics
                    </div>
                    <div class="flex items-center text-gray-500">
                        <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Mulai layani tamu!
                    </div>
                </div>
            </div>

            <!-- CTA Card -->
            <div class="step-card bg-gradient-to-br from-indigo-600 to-indigo-700 rounded-2xl shadow-lg p-6 text-white">
                <h3 class="text-xl font-bold mb-2">Siap Memulai?</h3>
                <p class="text-indigo-100 text-sm mb-6">Bergabung dengan ratusan bisnis yang sudah menggunakan Luma Network.</p>
                <div class="space-y-3">
                    <a href="/register" class="block w-full bg-white text-indigo-600 font-semibold py-3 px-4 rounded-lg text-center hover:bg-indigo-50 transition">
                        Daftar Sekarang
                    </a>
                    <a href="#" class="block w-full border border-indigo-400 text-white font-medium py-3 px-4 rounded-lg text-center hover:bg-indigo-500 transition">
                        Hubungi Sales
                    </a>
                </div>
            </div>

        </div>

        <!-- FAQ Section -->
        <div class="bg-white rounded-2xl shadow-md p-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Pertanyaan Umum</h2>
            <div class="space-y-4">
                <details class="group border-b border-gray-200 pb-4">
                    <summary class="flex justify-between items-center cursor-pointer font-semibold text-gray-900">
                        Berapa biaya langganan?
                        <svg class="w-5 h-5 text-gray-500 group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </summary>
                    <p class="text-gray-600 mt-2">Kami menawarkan paket mulai dari Rp 500.000/bulan tergantung jumlah router dan fitur yang dipilih. Hubungi sales untuk penawaran khusus.</p>
                </details>
                <details class="group border-b border-gray-200 pb-4">
                    <summary class="flex justify-between items-center cursor-pointer font-semibold text-gray-900">
                        Apakah perlu router khusus?
                        <svg class="w-5 h-5 text-gray-500 group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </summary>
                    <p class="text-gray-600 mt-2">Ya, kami mendukung MikroTik RouterOS v6 & v7. Routerboard seperti RB951, RB750, CCR series sudah teruji kompatibel.</p>
                </details>
                <details class="group border-b border-gray-200 pb-4">
                    <summary class="flex justify-between items-center cursor-pointer font-semibold text-gray-900">
                        Bagaimana dengan data tamu?
                        <svg class="w-5 h-5 text-gray-500 group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </summary>
                    <p class="text-gray-600 mt-2">Data tamu dienkripsi dan disimpan sesuai UU PDP. Kami tidak menjual data pelanggan Anda. Tersedia opsi data retention sesuai kebutuhan.</p>
                </details>
                <details class="group">
                    <summary class="flex justify-between items-center cursor-pointer font-semibold text-gray-900">
                        Apakah ada dukungan teknis?
                        <svg class="w-5 h-5 text-gray-500 group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </summary>
                    <p class="text-gray-600 mt-2">Ya, kami提供technical support via WhatsApp dan email. Paket premium mendapat akses prioritas dan dedicated account manager.</p>
                </details>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-12 text-gray-500 text-sm">
            <p>Butuh bantuan? <a href="mailto:support@luma.id" class="text-indigo-600 hover:underline">support@luma.id</a></p>
        </div>
    </div>

    <script>
        // Simple interactivity
        document.querySelectorAll(".step-card").forEach((card, index) => {
            card.addEventListener("click", () => {
                const progress = ((index + 1) / 5) * 100;
                document.getElementById("progress-bar").style.width = progress + "%";
                document.getElementById("progress-text").textContent = ;
            });
        });
    </script>
</body>
</html>
