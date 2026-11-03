<?php
// messages.php - Real-Time Customer Communication Center (Fully Responsive Mobile & Desktop)
require_once 'includes/auth_check.php';
require_once 'db_config.php';

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'Valued Client';

// Selected booking ID from URL or default
$selected_booking_id = intval($_GET['booking_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Customer Communication Center | JEMmerific</title>
    <link rel="icon" type="image/png" href="uploads/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F8FAFC; }
        .chat-scroll::-webkit-scrollbar { width: 5px; }
        .chat-scroll::-webkit-scrollbar-track { background: #F1F5F9; }
        .chat-scroll::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 4px; }
        .chat-scroll::-webkit-scrollbar-thumb:hover { background: #94A3B8; }
        /* Smooth touch scrolling for mobile */
        .touch-scroll { -webkit-overflow-scrolling: touch; }
    </style>
</head>
<body class="bg-[#F8FAFC] flex min-h-screen text-slate-800 antialiased overflow-x-hidden">

    <!-- Customer Sidebar Navigation -->
    <?php require_once 'includes/customer_sidebar.php'; ?>

    <!-- Main Workspace Container -->
    <div class="flex-1 lg:ml-64 flex flex-col h-[100dvh] overflow-hidden">
        
        <!-- Responsive Header Bar -->
        <header class="bg-white border-b border-slate-200/80 px-4 sm:px-6 py-3 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 z-10 shrink-0">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <button type="button" onclick="toggleSidebar()" class="lg:hidden text-slate-600 hover:text-slate-900 p-2 rounded-xl bg-slate-100 hover:bg-slate-200 active:scale-95 transition-all">
                        <i class="bi bi-list text-xl"></i>
                    </button>
                    <div>
                        <h1 class="text-base sm:text-lg font-extrabold text-slate-900 flex items-center gap-2">
                            <i class="bi bi-chat-dots-fill text-purple-600"></i> Communication Center
                        </h1>
                        <p class="text-[11px] sm:text-xs text-slate-500 font-medium hidden sm:block">Direct inquiry & file exchange with Jemmerific Catering</p>
                    </div>
                </div>

                <!-- Mobile Quick Info Toggle -->
                <button type="button" onclick="toggleMobileInfoDrawer()" class="md:hidden text-purple-700 bg-purple-50 hover:bg-purple-100 p-2 rounded-xl border border-purple-200 text-xs font-bold flex items-center gap-1">
                    <i class="bi bi-info-circle-fill text-sm"></i> Details
                </button>
            </div>

            <!-- Booking Select Box (Responsive width) -->
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <label for="bookingSelect" class="hidden md:block text-xs font-bold text-slate-500 uppercase tracking-wider shrink-0">Reservation:</label>
                <select id="bookingSelect" onchange="switchBooking(this.value)" class="w-full sm:w-64 bg-slate-50 border border-slate-200 text-slate-800 text-xs font-bold rounded-xl px-3 py-2 focus:ring-2 focus:ring-purple-500 focus:outline-none shadow-2xs truncate">
                    <option value="">Loading reservations...</option>
                </select>
            </div>
        </header>

        <!-- Main Chat Area Split -->
        <div class="flex-1 flex flex-col md:flex-row overflow-hidden relative">
            
            <!-- Messages Stream Column -->
            <div class="flex-1 flex flex-col bg-[#F1F5F9]/50 overflow-hidden relative">
                
                <!-- Booking Context Banner Header -->
                <div id="bookingContextBanner" class="bg-white border-b border-slate-200 px-4 sm:px-6 py-2.5 shadow-2xs flex flex-wrap items-center justify-between gap-2 text-xs hidden shrink-0">
                    <div class="flex items-center gap-3 overflow-hidden">
                        <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-purple-100 text-purple-700 flex items-center justify-center font-black shrink-0">
                            <i class="bi bi-calendar-event text-base sm:text-lg"></i>
                        </div>
                        <div class="overflow-hidden">
                            <div class="flex items-center gap-2 truncate">
                                <span id="bannerEventName" class="font-extrabold text-slate-900 text-xs sm:text-sm truncate"></span>
                                <span id="bannerBookingId" class="px-2 py-0.5 bg-purple-50 text-purple-700 font-bold text-[10px] rounded-full border border-purple-200 shrink-0"></span>
                            </div>
                            <p class="text-slate-500 text-[10px] sm:text-[11px] truncate">
                                <span id="bannerEventDate" class="font-semibold text-slate-700"></span> • 
                                <span id="bannerPax" class="font-semibold text-slate-700"></span> Guests • 
                                Status: <span id="bannerStatus" class="font-bold uppercase"></span>
                            </p>
                        </div>
                    </div>
                    <div class="hidden sm:flex items-center gap-2">
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Admin Online
                        </span>
                    </div>
                </div>

                <!-- Empty / No Booking Selected State -->
                <div id="emptyState" class="flex-1 flex flex-col items-center justify-center p-6 text-center">
                    <div class="w-16 h-16 sm:w-20 sm:h-20 bg-purple-50 text-purple-600 rounded-3xl flex items-center justify-center text-3xl sm:text-4xl mb-4 shadow-sm border border-purple-100">
                        <i class="bi bi-chat-square-heart"></i>
                    </div>
                    <h3 class="text-base sm:text-lg font-bold text-slate-800">No Booking Selected</h3>
                    <p class="text-xs text-slate-500 max-w-sm mt-1">Select a catering reservation from the menu above to communicate directly with our management team.</p>
                    <a href="dashboard.php" class="mt-4 inline-flex items-center gap-2 px-4 py-2.5 bg-purple-600 text-white font-bold text-xs rounded-xl hover:bg-purple-700 active:scale-95 transition-all shadow-md shadow-purple-200">
                        <i class="bi bi-plus-circle"></i> Create New Booking
                    </a>
                </div>

                <!-- Chat History Scroll Container -->
                <div id="chatHistory" class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-4 chat-scroll touch-scroll hidden">
                    <!-- Dynamic Messages -->
                </div>

                <!-- Typing Indicator Banner -->
                <div id="typingIndicator" class="px-4 sm:px-6 py-2 bg-white/90 text-xs font-semibold text-purple-600 italic hidden flex items-center gap-2 border-t border-slate-100 shrink-0">
                    <span class="flex gap-1">
                        <span class="w-1.5 h-1.5 bg-purple-500 rounded-full animate-bounce"></span>
                        <span class="w-1.5 h-1.5 bg-purple-500 rounded-full animate-bounce [animation-delay:0.2s]"></span>
                        <span class="w-1.5 h-1.5 bg-purple-500 rounded-full animate-bounce [animation-delay:0.4s]"></span>
                    </span>
                    Jemmerific Admin is typing...
                </div>

                <!-- File Attachment Selected Preview Strip -->
                <div id="filePreviewStrip" class="px-4 sm:px-6 py-2 bg-purple-50 border-t border-purple-100 hidden flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3 overflow-hidden">
                        <div id="filePreviewIcon" class="w-8 h-8 rounded-lg bg-purple-600 text-white flex items-center justify-center font-bold text-xs shrink-0">
                            <i class="bi bi-paperclip"></i>
                        </div>
                        <div class="overflow-hidden">
                            <p id="filePreviewName" class="text-xs font-bold text-slate-800 truncate"></p>
                            <p id="filePreviewSize" class="text-[10px] text-purple-600 font-semibold"></p>
                        </div>
                    </div>
                    <button type="button" onclick="clearFileAttachment()" class="text-slate-400 hover:text-red-500 p-1">
                        <i class="bi bi-x-circle-fill text-base"></i>
                    </button>
                </div>

                <!-- Mobile & Desktop Responsive Input Footer -->
                <div id="chatInputArea" class="bg-white dark:bg-[#131b2e] border-t border-slate-200 dark:border-slate-800 p-3 sm:p-4 z-10 hidden shrink-0">
                    <form id="messageForm" onsubmit="sendMessage(event)" class="flex items-end gap-2 sm:gap-3">
                        <!-- File Upload Trigger -->
                        <div class="relative">
                            <input type="file" id="fileInput" class="hidden" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.txt" onchange="handleFileSelect(event)">
                            <button type="button" onclick="document.getElementById('fileInput').click()" class="w-10 h-10 sm:w-11 sm:h-11 text-slate-500 dark:text-slate-400 hover:text-purple-600 dark:hover:text-purple-400 bg-slate-50 dark:bg-slate-900/60 hover:bg-purple-50 dark:hover:bg-purple-950/60 rounded-xl transition-all border border-slate-200 dark:border-slate-700 flex items-center justify-center shrink-0" title="Attach file or photo">
                                <i class="bi bi-paperclip text-lg"></i>
                            </button>
                        </div>

                        <!-- Textarea -->
                        <div class="flex-1 bg-slate-50 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-700 rounded-2xl px-3 py-2 focus-within:ring-2 focus-within:ring-purple-500 focus-within:border-transparent transition-all">
                            <textarea id="messageInput" rows="1" placeholder="Type message..." class="w-full bg-transparent text-xs sm:text-sm text-slate-800 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none resize-none max-h-28" style="background-color: transparent !important;" onkeydown="handleKeyDown(event)"></textarea>
                        </div>

                        <!-- Send Button -->
                        <button type="submit" id="sendBtn" class="w-10 h-10 sm:w-auto sm:px-5 sm:h-11 bg-purple-600 hover:bg-purple-700 active:scale-95 text-white font-bold text-xs rounded-xl sm:rounded-2xl flex items-center justify-center gap-2 transition-all shadow-md shadow-purple-600/20 shrink-0">
                            <span class="hidden sm:inline">Send</span>
                            <i class="bi bi-send-fill text-xs sm:text-sm"></i>
                        </button>
                    </form>
                    <p class="text-[9px] sm:text-[10px] text-slate-400 text-right mt-1 font-medium hidden sm:block">Upload photos (.jpg, .png), PDF, DOC, XLS, TXT</p>
                </div>
            </div>

            <!-- DESKTOP RIGHT SIDE PANEL: Reservation Details & Guidelines -->
            <div id="sideInfoPanel" class="w-80 bg-white border-l border-slate-200 p-6 flex-col gap-6 hidden md:flex overflow-y-auto shrink-0">
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Reservation Overview</h3>
                    <div id="sideBookingCard" class="bg-slate-50 rounded-2xl p-4 border border-slate-200/80 space-y-3">
                        <div>
                            <p class="text-[10px] font-bold text-purple-600 uppercase">Event Title</p>
                            <p id="sideEventName" class="text-sm font-extrabold text-slate-900"></p>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div>
                                <p class="text-[10px] text-slate-400 font-semibold">Event Date</p>
                                <p id="sideEventDate" class="font-bold text-slate-700"></p>
                            </div>
                            <div>
                                <p class="text-[10px] text-slate-400 font-semibold">Pax Count</p>
                                <p id="sidePax" class="font-bold text-slate-700"></p>
                            </div>
                        </div>
                        <div>
                            <p class="text-[10px] text-slate-400 font-semibold">Venue Location</p>
                            <p id="sideVenue" class="text-xs font-medium text-slate-700 line-clamp-2"></p>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Common Upload Files</h3>
                    <div class="space-y-2 text-xs text-slate-600">
                        <div class="flex items-center gap-2.5 p-2 rounded-xl bg-purple-50/50 border border-purple-100 text-purple-900 font-medium">
                            <i class="bi bi-image text-purple-600"></i> Venue layout / Inspiration photos
                        </div>
                        <div class="flex items-center gap-2.5 p-2 rounded-xl bg-slate-50 border border-slate-200/60 font-medium">
                            <i class="bi bi-file-earmark-pdf text-red-500"></i> Event Program & Schedule
                        </div>
                        <div class="flex items-center gap-2.5 p-2 rounded-xl bg-slate-50 border border-slate-200/60 font-medium">
                            <i class="bi bi-file-earmark-excel text-emerald-600"></i> Guest List Spreadsheet
                        </div>
                        <div class="flex items-center gap-2.5 p-2 rounded-xl bg-slate-50 border border-slate-200/60 font-medium">
                            <i class="bi bi-receipt text-indigo-600"></i> Payment Proof / Receipts
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MOBILE RESERVATION DETAILS DRAWER (Slide-up sheet for small screens) -->
    <div id="mobileInfoDrawer" class="fixed inset-0 z-[90] bg-slate-950/60 backdrop-blur-xs hidden md:hidden flex flex-col justify-end" onclick="toggleMobileInfoDrawer()">
        <div class="bg-white rounded-t-3xl p-6 max-h-[80vh] overflow-y-auto space-y-4" onclick="event.stopPropagation()">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-sm font-extrabold text-slate-900">Reservation Details</h3>
                <button type="button" onclick="toggleMobileInfoDrawer()" class="text-slate-400 hover:text-slate-600 p-1">
                    <i class="bi bi-x-lg text-lg"></i>
                </button>
            </div>
            <div class="bg-slate-50 rounded-2xl p-4 border border-slate-200/80 space-y-3">
                <div>
                    <p class="text-[10px] font-bold text-purple-600 uppercase">Event Title</p>
                    <p id="mSideEventName" class="text-sm font-extrabold text-slate-900"></p>
                </div>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div>
                        <p class="text-[10px] text-slate-400 font-semibold">Event Date</p>
                        <p id="mSideEventDate" class="font-bold text-slate-700"></p>
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-400 font-semibold">Pax Count</p>
                        <p id="mSidePax" class="font-bold text-slate-700"></p>
                    </div>
                </div>
                <div>
                    <p class="text-[10px] text-slate-400 font-semibold">Venue Location</p>
                    <p id="mSideVenue" class="text-xs font-medium text-slate-700"></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Full Image Preview Lightbox Modal -->
    <div id="imageModal" class="fixed inset-0 z-[100] bg-slate-950/90 backdrop-blur-md hidden flex items-center justify-center p-3 sm:p-6" onclick="closeImageModal()">
        <div class="relative w-full max-w-4xl max-h-[90vh] bg-white rounded-3xl overflow-hidden shadow-2xl flex flex-col" onclick="event.stopPropagation()">
            <div class="p-3 sm:p-4 bg-slate-900 text-white flex items-center justify-between border-b border-slate-800">
                <span id="modalImageTitle" class="text-xs font-bold truncate pr-2">Image Preview</span>
                <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                    <a id="modalDownloadBtn" href="" download class="px-3 py-1.5 bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs rounded-xl flex items-center gap-1.5 transition-all">
                        <i class="bi bi-download"></i> <span class="hidden sm:inline">Download</span>
                    </a>
                    <button onclick="closeImageModal()" class="text-slate-400 hover:text-white p-1">
                        <i class="bi bi-x-lg text-lg"></i>
                    </button>
                </div>
            </div>
            <div class="p-2 bg-slate-950 flex items-center justify-center overflow-auto max-h-[80vh]">
                <img id="modalImgTag" src="" alt="Full view" class="max-h-[75vh] w-auto object-contain rounded-xl">
            </div>
        </div>
    </div>

    <!-- JavaScript Logic -->
    <script>
        let currentBookingId = <?= $selected_booking_id ?>;
        let allMessages = [];
        let maxMessageId = 0;
        let isPolling = false;
        let pollTimer = null;
        let selectedFile = null;

        document.addEventListener('DOMContentLoaded', () => {
            loadCustomerBookings();

            // Intelligent InfinityFree traffic management:
            // Pause polling when tab is hidden, resume immediately when user returns
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden && currentBookingId) {
                    loadMessages(false);
                }
            });
        });

        // 1. Fetch User's Bookings
        async function loadCustomerBookings() {
            try {
                const res = await fetch('auth/chat_api.php?action=fetch_customer_bookings');
                const data = await res.json();
                
                if ((data.success || data.status === 'success') && data.bookings && data.bookings.length > 0) {
                    const select = document.getElementById('bookingSelect');
                    select.innerHTML = '';

                    let foundSelected = false;

                    data.bookings.forEach(b => {
                        const opt = document.createElement('option');
                        opt.value = b.booking_id;
                        opt.textContent = `Booking #${b.booking_id} - ${b.event_name} (${b.formatted_date}) ${b.unread_count > 0 ? '🔴' : ''}`;
                        select.appendChild(opt);

                        if (b.booking_id === currentBookingId) {
                            foundSelected = true;
                        }
                    });

                    if (!foundSelected) {
                        currentBookingId = data.bookings[0].booking_id;
                    }

                    select.value = currentBookingId;
                    switchBooking(currentBookingId);
                } else {
                    document.getElementById('bookingSelect').innerHTML = '<option value="">No Bookings Found</option>';
                    document.getElementById('emptyState').classList.remove('hidden');
                }
            } catch (err) {
                console.error("Error loading bookings:", err);
            }
        }

        // 2. Switch Active Booking
        function switchBooking(bookingId) {
            if (!bookingId) return;
            currentBookingId = parseInt(bookingId);
            allMessages = [];
            maxMessageId = 0;
            
            document.getElementById('emptyState').classList.add('hidden');
            document.getElementById('bookingContextBanner').classList.remove('hidden');
            document.getElementById('chatHistory').classList.remove('hidden');
            document.getElementById('chatInputArea').classList.remove('hidden');

            loadMessages(true);

            if (pollTimer) clearInterval(pollTimer);
            // Adaptive 4-second poll interval to respect InfinityFree hosting quotas
            pollTimer = setInterval(() => {
                if (!document.hidden) {
                    loadMessages(false);
                }
            }, 4000);
        }

        // 3. Load & Stream Messages (Zero-Waste Delta Polling)
        async function loadMessages(forceFull = false) {
            if (!currentBookingId || isPolling) return;

            isPolling = true;
            try {
                const afterParam = forceFull ? 0 : maxMessageId;
                const res = await fetch(`auth/chat_api.php?action=fetch_messages&booking_id=${currentBookingId}&after_id=${afterParam}`);
                const data = await res.json();

                if (!data.success && data.status !== 'success') {
                    if (forceFull) console.warn(data.message || data.error || 'Failed to load messages');
                    return;
                }

                if (forceFull && data.booking) {
                    updateBookingHeader(data.booking);
                }

                if (data.remaining_unread !== undefined) {
                    updateSidebarChatBadge(data.remaining_unread);
                }

                if (forceFull) {
                    allMessages = data.messages || [];
                    maxMessageId = 0;
                    allMessages.forEach(m => {
                        const mId = parseInt(m.id);
                        if (mId > maxMessageId) maxMessageId = mId;
                    });
                    renderMessages(allMessages);
                    scrollToBottom();
                } else if (data.has_new && data.messages && data.messages.length > 0) {
                    const shouldScroll = isNearBottom();
                    data.messages.forEach(m => {
                        const mId = parseInt(m.id);
                        if (mId > maxMessageId) maxMessageId = mId;
                        allMessages.push(m);
                    });
                    renderMessages(allMessages);
                    if (shouldScroll) {
                        scrollToBottom();
                    }
                }
            } catch (err) {
                console.error("Error loading messages:", err);
            } finally {
                isPolling = false;
            }
        }

        function updateSidebarChatBadge(count) {
            const sidebar = document.getElementById('sidebar');
            if (!sidebar) return;
            const badge = sidebar.querySelector('a[href="messages.php"] span.rounded-full');
            if (badge) {
                if (count > 0) {
                    badge.textContent = count;
                    badge.classList.remove('hidden');
                    badge.style.display = 'flex';
                } else {
                    badge.classList.add('hidden');
                    badge.style.display = 'none';
                }
            }
        }

        // Update Context Headers
        function updateBookingHeader(b) {
            document.getElementById('bannerEventName').textContent = b.event_name;
            document.getElementById('bannerBookingId').textContent = `Booking #${b.booking_id}`;
            document.getElementById('bannerEventDate').textContent = b.formatted_event_date;
            document.getElementById('bannerPax').textContent = b.pax_count;
            document.getElementById('bannerStatus').textContent = b.status;

            // Desktop Side Card
            document.getElementById('sideEventName').textContent = b.event_name;
            document.getElementById('sideEventDate').textContent = b.formatted_event_date;
            document.getElementById('sidePax').textContent = `${b.pax_count} Pax`;
            document.getElementById('sideVenue').textContent = b.venue_location || b.venue_address || 'Not specified';

            // Mobile Drawer
            document.getElementById('mSideEventName').textContent = b.event_name;
            document.getElementById('mSideEventDate').textContent = b.formatted_event_date;
            document.getElementById('mSidePax').textContent = `${b.pax_count} Pax`;
            document.getElementById('mSideVenue').textContent = b.venue_location || b.venue_address || 'Not specified';
        }

        // Render Message Bubbles
        function renderMessages(messages) {
            const container = document.getElementById('chatHistory');
            
            if (messages.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-12">
                        <div class="inline-block p-4 rounded-full bg-purple-50 text-purple-600 mb-2">
                            <i class="bi bi-chat-text text-2xl"></i>
                        </div>
                        <p class="text-xs font-bold text-slate-700">Start the conversation</p>
                        <p class="text-[11px] text-slate-400 max-w-xs mx-auto mt-1">Send a text message or attach details regarding your event layout, program, guest count, or payment.</p>
                    </div>`;
                return;
            }

            let html = '';
            let lastDate = '';

            messages.forEach(m => {
                if (m.date_formatted !== lastDate) {
                    html += `
                        <div class="flex items-center my-4">
                            <div class="flex-1 border-t border-slate-200"></div>
                            <span class="px-3 text-[10px] font-bold text-slate-400 uppercase tracking-widest bg-slate-100 py-1 rounded-full mx-2">${m.date_formatted}</span>
                            <div class="flex-1 border-t border-slate-200"></div>
                        </div>`;
                    lastDate = m.date_formatted;
                }

                const isMine = m.is_mine;
                const statusIcon = m.is_read == 1 
                    ? '<span class="text-purple-300 font-bold" title="Read"><i class="bi bi-check2-all text-xs"></i> Read</span>' 
                    : '<span class="text-purple-200/80 font-medium" title="Sent"><i class="bi bi-check2 text-xs"></i> Sent</span>';

                let senderLabel = 'Admin';
                if (isMine) {
                    senderLabel = 'You';
                } else if (m.sender_role === 'staff') {
                    senderLabel = `${escapeHtml(m.sender_name || 'Staff')} (Staff)`;
                } else if (m.sender_role === 'admin') {
                    senderLabel = `${escapeHtml(m.sender_name || 'Admin')} (Admin)`;
                } else {
                    senderLabel = escapeHtml(m.sender_display || m.sender_name || 'Customer Support');
                }

                html += `
                    <div class="flex flex-col ${isMine ? 'items-end' : 'items-start'} space-y-1">
                        <div class="flex items-center gap-2 px-1">
                            <span class="text-[10px] font-bold text-slate-500">${senderLabel}</span>
                            <span class="text-[9px] text-slate-400">${m.time_formatted}</span>
                        </div>
                        <div class="max-w-[90%] sm:max-w-[70%] rounded-2xl p-3.5 sm:p-4 shadow-2xs ${isMine ? 'bg-purple-600 text-white rounded-tr-xs' : 'bg-white text-slate-800 border border-slate-200/80 rounded-tl-xs'}">
                            ${m.message ? `<p class="text-xs sm:text-sm leading-relaxed whitespace-pre-wrap break-words">${escapeHtml(m.message)}</p>` : ''}
                            ${renderAttachment(m, isMine)}
                            ${isMine ? `<div class="text-[9px] text-right mt-1.5 flex items-center justify-end gap-1">${statusIcon}</div>` : ''}
                        </div>
                    </div>`;
            });

            container.innerHTML = html;
        }

        // Render Attachments
        function renderAttachment(msg, isMine) {
            if (!msg.attachment) return '';

            const filePath = msg.attachment;
            const fileType = msg.file_type || 'file';
            const fileName = filePath.split('/').pop();

            if (fileType === 'image') {
                return `
                    <div class="mt-2 group relative cursor-pointer inline-block overflow-hidden rounded-xl border ${isMine ? 'border-purple-500' : 'border-slate-200'}" onclick="openImageModal('${filePath}', '${fileName}')">
                        <img src="${filePath}" alt="Thumbnail" class="max-h-40 sm:max-h-48 w-auto object-cover group-hover:scale-105 transition-transform duration-300">
                        <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white font-bold text-xs gap-1.5">
                            <i class="bi bi-arrows-angle-expand"></i> Open Full Image
                        </div>
                    </div>`;
            } else {
                let iconClass = 'bi-file-earmark';
                let iconColor = 'text-slate-500';

                if (fileType === 'pdf') { iconClass = 'bi-file-earmark-pdf-fill'; iconColor = 'text-red-500'; }
                else if (fileType === 'docx') { iconClass = 'bi-file-earmark-word-fill'; iconColor = 'text-blue-500'; }
                else if (fileType === 'excel') { iconClass = 'bi-file-earmark-excel-fill'; iconColor = 'text-emerald-500'; }
                else if (fileType === 'txt') { iconClass = 'bi-file-earmark-text-fill'; iconColor = 'text-slate-600'; }

                return `
                    <div class="mt-2.5 p-2.5 sm:p-3 rounded-xl flex items-center justify-between gap-2.5 border ${isMine ? 'bg-purple-700/50 border-purple-500 text-white' : 'bg-slate-50 border-slate-200 text-slate-800'}">
                        <div class="flex items-center gap-2 overflow-hidden">
                            <i class="bi ${iconClass} text-lg sm:text-xl ${isMine ? 'text-purple-200' : iconColor}"></i>
                            <div class="overflow-hidden">
                                <p class="text-xs font-bold truncate max-w-[130px] sm:max-w-xs">${escapeHtml(fileName)}</p>
                                <p class="text-[9px] ${isMine ? 'text-purple-200' : 'text-slate-400'} uppercase font-bold">${fileType}</p>
                            </div>
                        </div>
                        <a href="${filePath}" download class="px-2.5 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1 transition-all ${isMine ? 'bg-white text-purple-700 hover:bg-purple-50' : 'bg-purple-600 text-white hover:bg-purple-700'}">
                            <i class="bi bi-download"></i> <span class="hidden sm:inline">Download</span>
                        </a>
                    </div>`;
            }
        }

        // File Selection Handlers
        function handleFileSelect(event) {
            const file = event.target.files[0];
            if (!file) return;

            selectedFile = file;
            document.getElementById('filePreviewStrip').classList.remove('hidden');
            document.getElementById('filePreviewName').textContent = file.name;
            document.getElementById('filePreviewSize').textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB';
        }

        function clearFileAttachment() {
            selectedFile = null;
            document.getElementById('fileInput').value = '';
            document.getElementById('filePreviewStrip').classList.add('hidden');
        }

        // Send Message
        async function sendMessage(e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }

            const messageInput = document.getElementById('messageInput');
            const messageText = messageInput.value.trim();

            if (!messageText && !selectedFile) return false;
            if (!currentBookingId) {
                alert("Please select or create a booking first before sending messages");
                return false;
            }

            const sendBtn = document.getElementById('sendBtn');
            if (sendBtn) sendBtn.disabled = true;

            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('booking_id', currentBookingId);
            formData.append('message', messageText);

            if (selectedFile) {
                formData.append('attachment', selectedFile);
            }

            try {
                const res = await fetch('auth/chat_api.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await res.json();

                if (data.success || data.status === 'success') {
                    messageInput.value = '';
                    messageInput.style.height = 'auto';
                    clearFileAttachment();
                    await loadMessages(true);
                } else {
                    alert(data.message || data.error || "Failed to send message");
                }
            } catch (err) {
                console.error("Error sending message:", err);
                alert("Connection error while sending message");
            } finally {
                if (sendBtn) sendBtn.disabled = false;
            }
            return false;
        }

        function handleKeyDown(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                e.stopPropagation();
                sendMessage(e);
                return false;
            }
        }

        // Mobile Drawer Toggle
        function toggleMobileInfoDrawer() {
            document.getElementById('mobileInfoDrawer').classList.toggle('hidden');
        }

        // Modal Lightbox for Images
        function openImageModal(path, name) {
            document.getElementById('modalImgTag').src = path;
            document.getElementById('modalImageTitle').textContent = name;
            document.getElementById('modalDownloadBtn').href = path;
            document.getElementById('imageModal').classList.remove('hidden');
        }

        function closeImageModal() {
            document.getElementById('imageModal').classList.add('hidden');
        }

        function isNearBottom() {
            const history = document.getElementById('chatHistory');
            return history.scrollHeight - history.scrollTop - history.clientHeight < 150;
        }

        function scrollToBottom() {
            const history = document.getElementById('chatHistory');
            history.scrollTop = history.scrollHeight;
        }

        function escapeHtml(str) {
            return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }
    </script>
</body>
</html>
