<?php 
require_once 'includes/auth_check.php'; 
require_once 'db_config.php';

// Fetch current user info
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'User';

// 1. Fetch Unread Notifications Count
$notif_query = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0";
$stmt = $conn->prepare($notif_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notif_count = $stmt->get_result()->fetch_assoc()['total'];

// 1.1 Fetch Latest 5 Notifications for Dropdown
$notif_list_query = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
$stmt_list = $conn->prepare($notif_list_query);
$stmt_list->bind_param("i", $user_id);
$stmt_list->execute();
$notifications_res = $stmt_list->get_result();

// 2. Fetch Booking Details
$booking_id = $_GET['booking_id'] ?? 0;
$total_to_pay = 0.00;
$full_total = 0.00;
$downpayment_required = 0.00;
$remaining_balance = 0.00;
$payment_label = "Total Downpayment";
$booking_data = null;

if ($booking_id > 0) {
    $b_query = "SELECT total_amount, payment_status FROM bookings WHERE booking_id = ? AND user_id = ?";
    $b_stmt = $conn->prepare($b_query);
    $b_stmt->bind_param("ii", $booking_id, $user_id);
    $b_stmt->execute();
    $b_res = $b_stmt->get_result();
    if($row = $b_res->fetch_assoc()) {
        $booking_data = $row;
        $full_total = (float)$row['total_amount'];
        $downpayment_required = min($full_total, 10000.00);
        $remaining_balance = $full_total - $downpayment_required;

        if ($row['payment_status'] == 'unpaid') {
            $total_to_pay = $downpayment_required;
            $payment_label = "Reservation Fee";
        } else {
            $total_to_pay = $remaining_balance;
            $payment_label = "Remaining Balance";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Checkout | JEMmerific</title>
    <link rel="icon" type="image/png" href="uploads/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        function applyAppTheme() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            if (savedTheme === 'dark') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        }
        applyAppTheme();
        window.addEventListener('storage', function(e) {
            if (e.key === 'theme') applyAppTheme();
        });
        document.addEventListener('DOMContentLoaded', applyAppTheme);
    </script>
    <style> 
        body { font-family: 'Plus Jakarta Sans', sans-serif; letter-spacing: -0.01em; } 
        .sidebar-link { transition: all 0.2s ease-in-out; }
        .sidebar-link:hover { background-color: #e5e7eb; color: #9b7cd1; padding-left: 1.5rem; }
        .active-link { background: linear-gradient(90deg, #d4cef3 0%, transparent 100%); color: #7c3aed; font-weight: 700; border-right: 4px solid #7c3aed; }
        input::placeholder { font-size: 0.8rem; opacity: 0.4; }
    </style>
</head>
<body class="bg-slate-50 dark:bg-[#0b0f19] transition-colors duration-300 min-h-screen text-slate-800 dark:text-slate-200 flex flex-col">

    <?php require_once 'includes/wizard_header.php'; ?>

    <main class="flex-1 max-w-6xl w-full mx-auto px-4 pb-16 transition-all">
        <header class="flex flex-col md:flex-row justify-between items-start gap-4 mb-8">
            <div>
                <span class="text-[10px] font-bold text-purple-600 dark:text-purple-300 uppercase tracking-widest bg-purple-50 dark:bg-purple-950/60 px-2.5 py-1 rounded-full border border-purple-100 dark:border-purple-800/50">Step 3 of 3</span>
                <h2 class="text-3xl font-extrabold text-slate-900 dark:text-slate-100 tracking-tight mt-2">Payment & Summary Checkout</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 font-semibold mt-1">Review your order summary and submit your payment proof to complete your booking.</p>
            </div>
            <div class="text-right">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Booking Ref</p>
                <p class="text-sm font-extrabold text-purple-600">#BK-<?= str_pad($booking_id, 4, '0', STR_PAD_LEFT) ?></p>
            </div>
        </header>

        <!-- Step Progress Bar (Step 3: Payment & Summary) -->
        <?php $active_step = 3; require_once 'includes/booking_steps.php'; ?>



        <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
            <div class="xl:col-span-2 space-y-8">
                
                <!-- Interactive Total Downpayment & Payment Amount Card -->
                <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 p-6 md:p-8 rounded-3xl text-white border border-slate-800 shadow-xl relative overflow-hidden">
                    <div class="relative z-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                        
                        <!-- Left: Dynamic Payment Display -->
                        <div>
                            <div class="flex items-center gap-2 mb-1.5">
                                <span class="w-2 h-2 rounded-full bg-purple-400 animate-pulse"></span>
                                <span id="payment-badge-label" class="text-xs font-extrabold uppercase tracking-widest text-purple-300">
                                    <?= ($booking_data['payment_status'] ?? 'unpaid') == 'unpaid' ? 'Required Downpayment (50%)' : 'Remaining Balance' ?>
                                </span>
                            </div>
                            <h3 id="display-payment-amount" class="text-4xl md:text-5xl font-black text-white tracking-tight">
                                ₱<?= number_format($downpayment_required, 2) ?>
                            </h3>
                            <p class="text-xs text-slate-400 font-semibold mt-1">
                                Event Full Total: <span class="text-white font-extrabold">₱<?= number_format($full_total, 2) ?></span>
                            </p>
                        </div>

                        <!-- Right: Interactive Payment Selection Tabs -->
                        <div class="flex flex-col sm:flex-row items-center gap-2 bg-white/10 p-1.5 rounded-2xl backdrop-blur-md border border-white/10 w-full md:w-auto">
                            <button type="button" id="tab-downpayment" onclick="setPaymentMode('downpayment', <?= $downpayment_required ?>)" 
                                     class="w-full sm:w-auto px-5 py-2.5 rounded-xl text-xs font-extrabold uppercase tracking-wider transition-all bg-purple-600 text-white shadow-md cursor-pointer">
                                50% Downpayment (₱<?= number_format($downpayment_required, 2) ?>)
                            </button>
                            <button type="button" id="tab-full" onclick="setPaymentMode('full', <?= $full_total ?>)" 
                                     class="w-full sm:w-auto px-5 py-2.5 rounded-xl text-xs font-extrabold uppercase tracking-wider transition-all text-slate-300 hover:text-white hover:bg-white/10 cursor-pointer">
                                Full Payment (₱<?= number_format($full_total, 2) ?>)
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Payment Form Card -->
                <div class="bg-white dark:bg-[#131b2e] p-5 sm:p-8 md:p-10 rounded-2xl sm:rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-xs">
                    <form id="payment-form" action="process_payment.php" method="POST" enctype="multipart/form-data" class="space-y-6">
                        <input type="hidden" name="booking_id" value="<?= $booking_id ?>">
                        <input type="hidden" name="payment_type" id="payment_type_input" value="downpayment">

                        <div>
                            <label class="block text-xs font-extrabold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-3">
                                Select Payment Method <span class="text-purple-600 font-bold text-[10px] ml-1">(4 Options Available)</span>
                            </label>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                <!-- GCash -->
                                <label class="cursor-pointer">
                                    <input type="radio" name="method" value="GCash" class="peer sr-only" checked onclick="toggleQR('gcash')">
                                    <div class="p-3.5 border-2 border-slate-200 dark:border-slate-700 rounded-2xl peer-checked:border-blue-600 dark:peer-checked:border-blue-500 peer-checked:bg-blue-50/50 dark:peer-checked:bg-blue-950/60 transition-all text-center group hover:border-slate-300 dark:hover:border-slate-600">
                                        <div class="w-8 h-8 mx-auto mb-1.5 rounded-full bg-blue-100 dark:bg-blue-900/50 text-blue-600 dark:text-blue-400 flex items-center justify-center font-black text-xs shadow-2xs">
                                            G
                                        </div>
                                        <span class="text-xs font-extrabold text-slate-900 dark:text-slate-100 uppercase tracking-wider block">GCash</span>
                                        <span class="text-[9px] text-slate-500 dark:text-slate-400 font-semibold block mt-0.5">E-Wallet</span>
                                    </div>
                                </label>

                                <!-- Maya -->
                                <label class="cursor-pointer">
                                    <input type="radio" name="method" value="Maya" class="peer sr-only" onclick="toggleQR('maya')">
                                    <div class="p-3.5 border-2 border-slate-200 dark:border-slate-700 rounded-2xl peer-checked:border-emerald-600 dark:peer-checked:border-emerald-500 peer-checked:bg-emerald-50/50 dark:peer-checked:bg-emerald-950/60 transition-all text-center group hover:border-slate-300 dark:hover:border-slate-600">
                                        <div class="w-8 h-8 mx-auto mb-1.5 rounded-full bg-emerald-100 dark:bg-emerald-900/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-black text-xs shadow-2xs">
                                            M
                                        </div>
                                        <span class="text-xs font-extrabold text-slate-900 dark:text-slate-100 uppercase tracking-wider block">Maya</span>
                                        <span class="text-[9px] text-slate-500 dark:text-slate-400 font-semibold block mt-0.5">E-Wallet</span>
                                    </div>
                                </label>

                                <!-- PayPal -->
                                <label class="cursor-pointer">
                                    <input type="radio" name="method" value="PayPal" class="peer sr-only" onclick="toggleQR('paypal')">
                                    <div class="p-3.5 border-2 border-slate-200 dark:border-slate-700 rounded-2xl peer-checked:border-sky-600 dark:peer-checked:border-sky-500 peer-checked:bg-sky-50/50 dark:peer-checked:bg-sky-950/60 transition-all text-center group hover:border-slate-300 dark:hover:border-slate-600">
                                        <div class="w-8 h-8 mx-auto mb-1.5 rounded-full bg-sky-100 dark:bg-sky-900/50 text-sky-600 dark:text-sky-400 flex items-center justify-center font-black text-xs shadow-2xs">
                                            <i class="bi bi-paypal"></i>
                                        </div>
                                        <span class="text-xs font-extrabold text-slate-900 dark:text-slate-100 uppercase tracking-wider block">PayPal</span>
                                        <span class="text-[9px] text-slate-500 dark:text-slate-400 font-semibold block mt-0.5">Online / Card</span>
                                    </div>
                                </label>

                                <!-- MariBank -->
                                <label class="cursor-pointer">
                                    <input type="radio" name="method" value="MariBank" class="peer sr-only" onclick="toggleQR('maribank')">
                                    <div class="p-3.5 border-2 border-slate-200 dark:border-slate-700 rounded-2xl peer-checked:border-orange-500 dark:peer-checked:border-orange-500 peer-checked:bg-orange-50/50 dark:peer-checked:bg-orange-950/60 transition-all text-center group hover:border-slate-300 dark:hover:border-slate-600">
                                        <div class="w-8 h-8 mx-auto mb-1.5 rounded-full bg-orange-100 dark:bg-orange-900/50 text-orange-600 dark:text-orange-400 flex items-center justify-center font-black text-xs shadow-2xs">
                                            <i class="bi bi-bank"></i>
                                        </div>
                                        <span class="text-xs font-extrabold text-slate-900 dark:text-slate-100 uppercase tracking-wider block">MariBank</span>
                                        <span class="text-[9px] text-slate-500 dark:text-slate-400 font-semibold block mt-0.5">Digital Bank</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-4">
                                <div>
                                    <label id="account-name-label" class="block text-xs font-extrabold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Sender Account Name</label>
                                    <input type="text" name="account_name" id="account_name_input" required placeholder="Full Name on GCash Account" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900/70 border border-slate-200 dark:border-slate-700 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:border-purple-600 outline-none transition font-bold text-sm text-slate-900 dark:text-slate-100">
                                </div>
                                <div>
                                    <label id="ref-no-label" class="block text-xs font-extrabold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Reference / Transaction No.</label>
                                    <input type="text" name="ref_no" id="ref_no_input" required placeholder="Reference ID e.g. 100234918" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900/70 border border-slate-200 dark:border-slate-700 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:border-purple-600 outline-none transition font-bold text-sm text-slate-900 dark:text-slate-100">
                                </div>
                            </div>
                            <div class="space-y-4">
                                <div>
                                    <label id="mobile-number-label" class="block text-xs font-extrabold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Sender Mobile / Account No.</label>
                                    <input type="text" name="mobile_number" id="mobile_number_input" required placeholder="09XXXXXXXXX (GCash Mobile)" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900/70 border border-slate-200 dark:border-slate-700 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:border-purple-600 outline-none transition font-bold text-sm text-slate-900 dark:text-slate-100">
                                </div>
                                <div>
                                    <label class="block text-xs font-extrabold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Upload Payment Proof / Screenshot</label>
                                    <div class="relative group">
                                        <input type="file" id="screenshot-input" name="screenshot" accept="image/*" required class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-20">
                                        <div class="w-full px-4 py-3 bg-purple-50/60 dark:bg-purple-950/40 border-2 border-dashed border-purple-200 dark:border-purple-800/60 rounded-xl text-center group-hover:bg-purple-100/50 dark:group-hover:bg-purple-900/40 transition-all cursor-pointer">
                                            <p id="file-text" class="text-xs font-extrabold text-purple-700 dark:text-purple-300 uppercase tracking-wider">
                                                <i class="bi bi-cloud-upload-fill mr-1"></i> Attach Receipt Image
                                            </p>
                                        </div>
                                    </div>
                                    <div id="preview-container" class="hidden mt-3"><img id="image-preview" src="#" class="w-12 h-12 object-cover rounded-lg border border-slate-200 dark:border-slate-700 shadow-2xs"></div>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-3 pt-2">
                            <button type="button" onclick="confirmCancel()" class="w-full sm:flex-1 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold py-3.5 rounded-xl transition-all uppercase text-xs tracking-wider cursor-pointer flex items-center justify-center gap-1.5 min-h-[44px]">
                                <i class="bi bi-arrow-left"></i> Back to Menu Selection
                            </button>
                            <button type="submit" id="submit-btn" class="w-full sm:flex-1 bg-purple-600 hover:bg-purple-700 text-white font-extrabold py-3.5 rounded-xl shadow-md transition-all uppercase text-xs tracking-wider cursor-pointer flex items-center justify-center gap-2 min-h-[44px]">
                                <i class="bi bi-check-circle-fill"></i> Confirm &amp; Submit Payment
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Generated Payment QR Code Side Card -->
            <div class="space-y-6">
                <div id="qr-container" class="bg-white dark:bg-[#131b2e] p-6 rounded-3xl text-center border border-slate-200/80 dark:border-slate-800 shadow-xs relative overflow-hidden transition-all">
                    <div id="qr-accent" class="absolute top-0 left-0 w-full h-1.5 bg-blue-600"></div>
                    
                    <div id="qr-badge" class="inline-flex items-center gap-1.5 px-3 py-1 bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 rounded-full text-xs font-extrabold uppercase tracking-wider mb-4 border border-blue-100 dark:border-blue-800/50">
                        <i class="bi bi-qr-code-scan text-sm"></i> <span id="qr-label">Official GCash QR</span>
                    </div>

                    <!-- Dynamic Generated QR Code Image -->
                    <div class="p-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700/80 rounded-2xl inline-block mb-3 relative shadow-sm">
                        <img id="qr-image" 
                             src="https://api.qrserver.com/v1/create-qr-code/?size=240x240&margin=10&data=GCASH-JEMMERIFIC-BOOKING-<?= $booking_id ?>-AMOUNT-<?= $downpayment_required ?>" 
                             alt="Generated QR Code" 
                             crossorigin="anonymous"
                             class="w-44 h-44 object-contain rounded-lg transition-opacity duration-300">
                    </div>

                    <div class="space-y-1 mb-4">
                        <h4 class="text-sm font-extrabold text-slate-900 dark:text-slate-100" id="merchant-name">JEMmerific Catering Services</h4>
                        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400" id="account-no-display">
                            Account: <span class="text-blue-700 dark:text-blue-400 font-extrabold">0917-892-3456</span>
                        </p>
                    </div>

                    <!-- Section of Copy Image on each QR code -->
                    <div class="pt-3 border-t border-slate-100 dark:border-slate-800/80 space-y-2">
                        <div class="flex items-center justify-between text-[11px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1 px-1">
                            <span><i class="bi bi-images text-purple-600 mr-1"></i> QR Code Actions</span>
                            <span class="text-[10px] text-purple-600 font-bold">Quick Scan</span>
                        </div>

                        <!-- Primary: Copy Image Button -->
                        <button type="button" id="copy-qr-img-btn" onclick="copyQrImage()" 
                                class="w-full py-2.5 px-3 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-extrabold uppercase tracking-wider shadow-sm hover:shadow transition-all flex items-center justify-center gap-2 cursor-pointer">
                            <i class="bi bi-copy text-sm"></i> <span id="copy-qr-text">Copy QR Image</span>
                        </button>

                        <!-- Secondary Action Buttons: Save & Copy Account -->
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" onclick="downloadQrImage()" 
                                    class="py-2 px-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-[11px] font-bold uppercase tracking-wider transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                                <i class="bi bi-download"></i> Save Image
                            </button>
                            <button type="button" id="copy-acc-btn" onclick="copyAccountNo()" 
                                    class="py-2 px-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-[11px] font-bold uppercase tracking-wider transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                                <i class="bi bi-hash"></i> Copy Account
                            </button>
                        </div>

                        <p class="text-[10px] text-slate-500 dark:text-slate-400 text-center pt-1 font-medium leading-relaxed">
                            <i class="bi bi-shield-check text-emerald-500 mr-0.5"></i> Copy image or download to scan directly inside your banking app.
                        </p>
                    </div>
                </div>

                <button type="button" onclick="openSummaryModal()" class="w-full py-3.5 bg-white dark:bg-[#131b2e] border border-slate-200/80 dark:border-slate-800 text-slate-700 dark:text-slate-200 hover:text-purple-600 dark:hover:text-purple-400 rounded-xl font-bold text-xs uppercase tracking-wider shadow-2xs hover:bg-slate-50 dark:hover:bg-slate-800 transition-all cursor-pointer flex items-center justify-center gap-2">
                    <i class="bi bi-journal-text text-base"></i> Review Order Summary
                </button>
            </div>
        </div>
    </main>

    <div id="summaryModal" class="fixed inset-0 z-[100] hidden">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeSummaryModal()"></div>
        <div class="relative w-full max-w-4xl mx-auto my-12 bg-white rounded-3xl shadow-2xl overflow-hidden h-[80vh] flex flex-col">
            <iframe id="summaryFrame" src="" class="w-full flex-1"></iframe>
        </div>
    </div>

    <script>
        let currentMethod = 'gcash';
        let currentAmount = <?= $downpayment_required ?>;
        const bookingId = <?= $booking_id ?>;
        const downpaymentAmt = <?= $downpayment_required ?>;
        const fullAmt = <?= $full_total ?>;

        const methodConfig = {
            gcash: {
                name: 'GCash',
                label: 'Official GCash QR',
                merchant: 'JEMmerific Catering Services',
                account: '0917-892-3456',
                accentClass: 'absolute top-0 left-0 w-full h-1.5 bg-blue-600',
                badgeClass: 'inline-flex items-center gap-1.5 px-3 py-1 bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 rounded-full text-xs font-extrabold uppercase tracking-wider mb-4 border border-blue-100 dark:border-blue-800/50',
                accountHtml: 'Account: <span class="text-blue-700 dark:text-blue-400 font-extrabold">0917-892-3456</span>',
                qrData: (id, amt) => `GCASH-JEMMERIFIC-BOOKING-${id}-AMOUNT-${amt}`,
                mobileLabel: 'Sender GCash Mobile No.',
                mobilePlaceholder: '09XXXXXXXXX (GCash Mobile)',
                nameLabel: 'Sender Account Name',
                namePlaceholder: 'Full Name on GCash Account',
                refPlaceholder: 'GCash Reference No. (e.g. 100234918)'
            },
            maya: {
                name: 'Maya',
                label: 'Official Maya QR',
                merchant: 'JEMmerific Catering Services',
                account: '0918-543-9876',
                accentClass: 'absolute top-0 left-0 w-full h-1.5 bg-emerald-500',
                badgeClass: 'inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 rounded-full text-xs font-extrabold uppercase tracking-wider mb-4 border border-emerald-100 dark:border-emerald-800/50',
                accountHtml: 'Account: <span class="text-emerald-700 dark:text-emerald-400 font-extrabold">0918-543-9876</span>',
                qrData: (id, amt) => `MAYA-JEMMERIFIC-BOOKING-${id}-AMOUNT-${amt}`,
                mobileLabel: 'Sender Maya Mobile No.',
                mobilePlaceholder: '09XXXXXXXXX (Maya Mobile)',
                nameLabel: 'Sender Account Name',
                namePlaceholder: 'Full Name on Maya Account',
                refPlaceholder: 'Maya Reference / Receipt ID'
            },
            paypal: {
                name: 'PayPal',
                label: 'Official PayPal QR & Account',
                merchant: 'JEMmerific Catering Inc.',
                account: 'payments@jemmerific.com',
                accentClass: 'absolute top-0 left-0 w-full h-1.5 bg-sky-500',
                badgeClass: 'inline-flex items-center gap-1.5 px-3 py-1 bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 rounded-full text-xs font-extrabold uppercase tracking-wider mb-4 border border-sky-100 dark:border-sky-800/50',
                accountHtml: 'Account: <span class="text-sky-700 dark:text-sky-400 font-extrabold">payments@jemmerific.com (@jemmerific)</span>',
                qrData: (id, amt) => `https://paypal.me/jemmerific/${amt}`,
                mobileLabel: 'Sender PayPal Email / Phone',
                mobilePlaceholder: 'your-email@example.com or phone',
                nameLabel: 'Sender Account Name',
                namePlaceholder: 'PayPal Profile / Account Name',
                refPlaceholder: 'PayPal Transaction ID (e.g. 9XX123456789)'
            },
            maribank: {
                name: 'MariBank',
                label: 'Official MariBank QR (SeaBank / MariBank)',
                merchant: 'JEMmerific Catering Services',
                account: '1092-8834-5671',
                accentClass: 'absolute top-0 left-0 w-full h-1.5 bg-orange-500',
                badgeClass: 'inline-flex items-center gap-1.5 px-3 py-1 bg-orange-50 dark:bg-orange-950/60 text-orange-700 dark:text-orange-300 rounded-full text-xs font-extrabold uppercase tracking-wider mb-4 border border-orange-100 dark:border-orange-800/50',
                accountHtml: 'Account: <span class="text-orange-700 dark:text-orange-400 font-extrabold">1092-8834-5671</span>',
                qrData: (id, amt) => `MARIBANK-JEMMERIFIC-BOOKING-${id}-AMOUNT-${amt}`,
                mobileLabel: 'Sender MariBank Mobile / Account No.',
                mobilePlaceholder: 'MariBank Mobile or 12-digit Account No.',
                nameLabel: 'Sender Account Name',
                namePlaceholder: 'MariBank Account Holder Name',
                refPlaceholder: 'MariBank Reference / Transfer ID'
            }
        };

        function setPaymentMode(mode, amount) {
            currentAmount = amount;
            document.getElementById('payment_type_input').value = mode;
            
            const btnDp = document.getElementById('tab-downpayment');
            const btnFull = document.getElementById('tab-full');
            const label = document.getElementById('payment-badge-label');
            const display = document.getElementById('display-payment-amount');

            if (mode === 'downpayment') {
                label.innerText = 'Required Downpayment (50%)';
                display.innerText = '₱' + amount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                btnDp.className = 'w-full sm:w-auto px-5 py-2.5 rounded-xl text-xs font-extrabold uppercase tracking-wider transition-all bg-purple-600 text-white shadow-md cursor-pointer';
                btnFull.className = 'w-full sm:w-auto px-5 py-2.5 rounded-xl text-xs font-extrabold uppercase tracking-wider transition-all text-slate-300 hover:text-white hover:bg-white/10 cursor-pointer';
            } else {
                label.innerText = 'Full Amount Payment (100%)';
                display.innerText = '₱' + amount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                btnFull.className = 'w-full sm:w-auto px-5 py-2.5 rounded-xl text-xs font-extrabold uppercase tracking-wider transition-all bg-purple-600 text-white shadow-md cursor-pointer';
                btnDp.className = 'w-full sm:w-auto px-5 py-2.5 rounded-xl text-xs font-extrabold uppercase tracking-wider transition-all text-slate-300 hover:text-white hover:bg-white/10 cursor-pointer';
            }

            updateGeneratedQR();
        }

        function toggleQR(method) {
            currentMethod = method.toLowerCase();
            updateGeneratedQR();
        }

        function updateGeneratedQR() {
            const conf = methodConfig[currentMethod] || methodConfig.gcash;
            const qrImg = document.getElementById('qr-image');
            const qrLabel = document.getElementById('qr-label');
            const qrBadge = document.getElementById('qr-badge');
            const qrAccent = document.getElementById('qr-accent');
            const merchantName = document.getElementById('merchant-name');
            const accountDisplay = document.getElementById('account-no-display');

            // Dynamic Form Labels and Placeholders
            const mobileLabel = document.getElementById('mobile-number-label');
            const mobileInput = document.getElementById('mobile_number_input');
            const nameLabel = document.getElementById('account-name-label');
            const nameInput = document.getElementById('account_name_input');
            const refInput = document.getElementById('ref_no_input');

            if (mobileLabel && mobileInput) {
                mobileLabel.innerText = conf.mobileLabel;
                mobileInput.placeholder = conf.mobilePlaceholder;
            }
            if (nameLabel && nameInput) {
                nameLabel.innerText = conf.nameLabel;
                nameInput.placeholder = conf.namePlaceholder;
            }
            if (refInput) {
                refInput.placeholder = conf.refPlaceholder;
            }

            const qrDataStr = conf.qrData(bookingId, currentAmount);
            qrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=240x240&margin=10&data=${encodeURIComponent(qrDataStr)}`;

            qrLabel.innerText = conf.label;
            qrBadge.className = conf.badgeClass;
            qrAccent.className = conf.accentClass;
            merchantName.innerText = conf.merchant;
            accountDisplay.innerHTML = conf.accountHtml;
        }

        // Section: Copy QR Image, Save Image & Copy Account Actions
        async function copyQrImage() {
            const copyBtn = document.getElementById('copy-qr-img-btn');
            const copyText = document.getElementById('copy-qr-text');
            const originalHtml = copyBtn.innerHTML;

            try {
                const qrImg = document.getElementById('qr-image');
                
                // Fetch image as blob
                const response = await fetch(qrImg.src, { mode: 'cors' });
                const blob = await response.blob();

                if (navigator.clipboard && window.ClipboardItem && navigator.clipboard.write) {
                    let pngBlob = blob;
                    if (blob.type !== 'image/png') {
                        pngBlob = await convertBlobToPng(blob);
                    }
                    await navigator.clipboard.write([
                        new ClipboardItem({ 'image/png': pngBlob })
                    ]);
                    triggerCopySuccess(copyBtn, originalHtml);
                } else {
                    downloadQrImage();
                }
            } catch (err) {
                console.warn('Direct clipboard image copy failed, using canvas fallback:', err);
                try {
                    const canvas = document.createElement('canvas');
                    const img = new Image();
                    img.crossOrigin = 'anonymous';
                    img.onload = async () => {
                        canvas.width = img.naturalWidth || 240;
                        canvas.height = img.naturalHeight || 240;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0);
                        canvas.toBlob(async (b) => {
                            if (b && navigator.clipboard && window.ClipboardItem && navigator.clipboard.write) {
                                try {
                                    await navigator.clipboard.write([new ClipboardItem({ 'image/png': b })]);
                                    triggerCopySuccess(copyBtn, originalHtml);
                                    return;
                                } catch (e) { /* fallback */ }
                            }
                            downloadQrImage();
                        }, 'image/png');
                    };
                    img.src = document.getElementById('qr-image').src;
                } catch (e2) {
                    downloadQrImage();
                }
            }
        }

        function triggerCopySuccess(btn, originalHtml) {
            btn.classList.remove('bg-purple-600', 'hover:bg-purple-700');
            btn.classList.add('bg-emerald-600', 'hover:bg-emerald-700');
            btn.innerHTML = `<i class="bi bi-check2-circle text-sm"></i> QR Image Copied!`;
            
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'QR Code image copied to clipboard!',
                text: 'Paste directly into your bank or e-wallet app.',
                showConfirmButton: false,
                timer: 3000
            });

            setTimeout(() => {
                btn.classList.remove('bg-emerald-600', 'hover:bg-emerald-700');
                btn.classList.add('bg-purple-600', 'hover:bg-purple-700');
                btn.innerHTML = originalHtml;
            }, 2500);
        }

        function convertBlobToPng(blob) {
            return new Promise((resolve) => {
                const img = new Image();
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    canvas.width = img.naturalWidth || 240;
                    canvas.height = img.naturalHeight || 240;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0);
                    canvas.toBlob(b => resolve(b), 'image/png');
                };
                img.src = URL.createObjectURL(blob);
            });
        }

        function downloadQrImage() {
            const qrImg = document.getElementById('qr-image');
            fetch(qrImg.src, { mode: 'cors' })
                .then(res => res.blob())
                .then(blob => {
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = url;
                    a.download = `JEMmerific_${currentMethod.toUpperCase()}_Payment_QR.png`;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                    
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'QR code saved to your device!',
                        showConfirmButton: false,
                        timer: 2200
                    });
                })
                .catch(() => {
                    window.open(qrImg.src, '_blank');
                });
        }

        function copyAccountNo() {
            const conf = methodConfig[currentMethod] || methodConfig.gcash;
            const acc = conf.account;
            if (!acc) return;
            navigator.clipboard.writeText(acc).then(() => {
                const btn = document.getElementById('copy-acc-btn');
                const orig = btn.innerHTML;
                btn.innerHTML = `<i class="bi bi-check2 text-emerald-600"></i> Copied!`;
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: `Account copied: ${acc}`,
                    showConfirmButton: false,
                    timer: 2000
                });
                setTimeout(() => { btn.innerHTML = orig; }, 2000);
            });
        }

        const fileInput = document.getElementById('screenshot-input');
        fileInput.onchange = evt => {
            const [file] = fileInput.files;
            if (file) {
                document.getElementById('image-preview').src = URL.createObjectURL(file);
                document.getElementById('preview-container').classList.remove('hidden');
            }
        }

        function toggleNotifDropdown() { document.getElementById('notif-dropdown').classList.toggle('hidden'); }
        function toggleSidebar() { 
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
            document.getElementById('sidebarOverlay').classList.toggle('hidden');
        }
        function openSummaryModal() {
            document.getElementById('summaryFrame').src = 'summary.php?booking_id=<?= $booking_id ?>&modal=1';
            document.getElementById('summaryModal').classList.remove('hidden');
        }
        function closeSummaryModal() { document.getElementById('summaryModal').classList.add('hidden'); }

        function confirmCancel() {
            Swal.fire({
                title: 'Back to Menu Selection?',
                text: "Your booking details and menu selections are safely saved.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#7c3aed',
                cancelButtonColor: '#94a3b8',
                confirmButtonText: 'Yes, back to Menu',
                cancelButtonText: 'Stay on Payment',
                customClass: { popup: 'rounded-2xl' }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'menu.php?booking_id=<?= $booking_id ?>';
                }
            });
        }

        document.getElementById('payment-form').onsubmit = async function(e) {
            e.preventDefault();
            const btn = document.getElementById('submit-btn');
            btn.disabled = true;
            btn.innerHTML = `<i class="bi bi-arrow-repeat animate-spin"></i> Submitting...`;
            try {
                const formData = new FormData(this);
                const response = await fetch(this.action, { method: 'POST', body: formData });
                const result = await response.json();
                if (result.status === 'success') {
                    Swal.fire({ 
                        title: 'Payment Submitted!', 
                        text: 'Your payment verification is being processed.', 
                        icon: 'success', 
                        confirmButtonColor: '#7c3aed', 
                        customClass: { popup: 'rounded-[2rem]' } 
                    }).then(() => window.location.href = 'dashboard.php');
                } else { throw new Error(result.message); }
            } catch (error) {
                Swal.fire({ title: 'Error', text: error.message, icon: 'error', customClass: { popup: 'rounded-[2rem]' } });
                btn.disabled = false;
                btn.innerHTML = `<i class="bi bi-check-circle-fill"></i> Confirm & Submit Payment`;
            }
        };
    </script>
</body>
</html>
