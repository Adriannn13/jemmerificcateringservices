<?php
// admin_messages.php - Admin Communication Center (Fully Responsive Mobile & Desktop Messenger)
require_once 'includes/admin_auth_check.php';
require_once 'db_config.php';

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'System Admin';

// Initial pre-selected booking ID from GET parameter if any
$selected_booking_id = intval($_GET['booking_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin Communication Center | JEMmerific</title>
    <link rel="icon" type="image/png" href="uploads/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F8FAFC; }
        .chat-scroll::-webkit-scrollbar { width: 5px; }
        .chat-scroll::-webkit-scrollbar-track { background: #F1F5F9; }
        .chat-scroll::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 4px; }
        .chat-scroll::-webkit-scrollbar-thumb:hover { background: #94A3B8; }
        .conv-item.active { background-color: #F3E8FF; border-color: #D8B4FE; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .touch-scroll { -webkit-overflow-scrolling: touch; }
    </style>
</head>
<body class="bg-[#F8FAFC] flex min-h-screen text-slate-800 antialiased overflow-x-hidden">

    <!-- Admin Sidebar Navigation -->
    <?php require_once 'includes/admin_sidebar.php'; ?>

    <!-- Main Workspace Container -->
    <div class="flex-1 lg:ml-64 flex flex-col h-[100dvh] overflow-hidden">

        <!-- Responsive Header Bar -->
        <header class="bg-white border-b border-slate-200/80 px-4 sm:px-6 py-3 flex items-center justify-between z-10 shrink-0">
            <div class="flex items-center gap-3">
                <button type="button" onclick="toggleSidebar()" class="lg:hidden text-slate-600 hover:text-slate-900 p-2 rounded-xl bg-slate-100 hover:bg-slate-200 active:scale-95 transition-all">
                    <i class="bi bi-list text-xl"></i>
                </button>
                <div>
                    <h1 class="text-base sm:text-lg font-extrabold text-slate-900 flex items-center gap-2">
                        <i class="bi bi-chat-left-text-fill text-purple-600"></i> Communication Center
                    </h1>
                    <p class="text-[11px] sm:text-xs text-slate-500 font-medium hidden sm:block">Centralized customer messaging dashboard</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] sm:text-xs font-extrabold bg-purple-50 text-purple-700 border border-purple-200">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Live Sync
                </span>
            </div>
        </header>

        <!-- Messenger Style Split Layout -->
        <div class="flex-1 flex overflow-hidden relative">

            <!-- LEFT COLUMN: Conversation Directory -->
            <div id="convCol" class="w-full md:w-72 lg:w-80 bg-white border-r border-slate-200 flex flex-col shrink-0">
                
                <!-- Search & Status Filter Header -->
                <div class="p-3 sm:p-4 border-b border-slate-200/80 space-y-2.5 bg-slate-50/50 shrink-0">
                    <!-- Search Input -->
                    <div class="relative">
                        <i class="bi bi-search absolute left-3.5 top-2.5 text-slate-400 text-xs"></i>
                        <input type="text" id="searchConv" oninput="filterConversations()" placeholder="Search customer or booking #..." class="w-full pl-9 pr-4 py-2 bg-white border border-slate-200 rounded-xl text-xs font-semibold placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-purple-500 shadow-2xs">
                    </div>

                    <!-- Filter Tabs -->
                    <div class="flex items-center gap-1 overflow-x-auto pb-1 no-scrollbar text-xs font-bold whitespace-nowrap">
                        <button onclick="setStatusFilter('all')" id="filter-all" class="px-3 py-1.5 rounded-xl transition-all bg-purple-600 text-white shadow-2xs">All</button>
                        <button onclick="setStatusFilter('pending')" id="filter-pending" class="px-3 py-1.5 rounded-xl transition-all text-slate-600 hover:bg-slate-100">Pending</button>
                        <button onclick="setStatusFilter('approved')" id="filter-approved" class="px-3 py-1.5 rounded-xl transition-all text-slate-600 hover:bg-slate-100">Approved</button>
                        <button onclick="setStatusFilter('completed')" id="filter-completed" class="px-3 py-1.5 rounded-xl transition-all text-slate-600 hover:bg-slate-100">Completed</button>
                        <button onclick="setStatusFilter('cancelled')" id="filter-cancelled" class="px-3 py-1.5 rounded-xl transition-all text-slate-600 hover:bg-slate-100">Cancelled</button>
                        <button onclick="setStatusFilter('archived')" id="filter-archived" class="px-3 py-1.5 rounded-xl transition-all text-slate-600 hover:bg-slate-100">Archived</button>
                    </div>
                </div>

                <!-- Scrollable Conversation Directory -->
                <div id="convList" class="flex-1 overflow-y-auto divide-y divide-slate-100 chat-scroll touch-scroll">
                    <div class="p-8 text-center text-slate-400 text-xs">
                        <i class="bi bi-arrow-repeat animate-spin text-xl inline-block mb-2 text-purple-600"></i>
                        <p>Loading customer conversations...</p>
                    </div>
                </div>
            </div>

            <!-- CENTER COLUMN: Active Chat Panel -->
            <div id="chatCol" class="hidden md:flex flex-1 flex-col bg-[#F1F5F9]/50 overflow-hidden relative">
                
                <!-- Chat Header Bar -->
                <div id="chatHeader" class="bg-white border-b border-slate-200 px-4 sm:px-6 py-2.5 shadow-2xs flex items-center justify-between hidden z-10 shrink-0">
                    <div class="flex items-center gap-3 overflow-hidden">
                        <!-- Mobile Back to Conversations Button -->
                        <button type="button" onclick="showConvListOnMobile()" class="md:hidden text-purple-700 bg-purple-50 hover:bg-purple-100 p-2 rounded-xl border border-purple-200 text-xs font-bold flex items-center gap-1 shrink-0">
                            <i class="bi bi-chevron-left text-sm"></i> Back
                        </button>

                        <div id="activeAvatar" class="w-9 h-9 sm:w-10 sm:h-10 rounded-2xl bg-purple-600 text-white font-black flex items-center justify-center text-sm sm:text-base uppercase shadow-sm shrink-0">
                            ?
                        </div>
                        <div class="overflow-hidden">
                            <div class="flex items-center gap-2 truncate">
                                <h2 id="activeCustomerName" class="text-xs sm:text-sm font-extrabold text-slate-900 truncate"></h2>
                                <span id="activeBookingBadge" class="px-2 py-0.5 bg-purple-50 text-purple-700 font-bold text-[10px] rounded-full border border-purple-200 shrink-0"></span>
                            </div>
                            <p class="text-slate-500 text-[10px] sm:text-[11px] truncate mt-0.5">
                                <span id="activeEventName" class="font-bold text-slate-800"></span> • 
                                <span id="activeEventDate" class="font-semibold text-slate-600"></span> • 
                                <span id="activeStatusPill" class="font-bold uppercase text-[9px] sm:text-[10px] px-2 py-0.5 rounded-full"></span>
                            </p>
                        </div>
                    </div>

                    <!-- Right Controls / Mobile Tools Trigger -->
                    <div class="flex items-center gap-1 sm:gap-2">
                        <!-- Desktop Side Panel Toggle -->
                        <!-- Action Buttons -->
                        <button type="button" id="btn-unarchive" onclick="unarchiveCurrentChat()" class="hidden p-2 text-slate-500 hover:text-green-600 hover:bg-green-50 rounded-xl transition-all" title="Unarchive Chat">
                            <i class="bi bi-box-arrow-up text-lg"></i>
                        </button>
                        <button type="button" id="btn-archive" onclick="archiveCurrentChat()" class="hidden md:flex p-2 text-slate-500 hover:text-orange-500 hover:bg-orange-50 rounded-xl transition-all" title="Archive Chat">
                            <i class="bi bi-archive text-lg"></i>
                        </button>
                        <button type="button" onclick="deleteCurrentChat()" class="hidden md:flex p-2 text-slate-500 hover:text-red-500 hover:bg-red-50 rounded-xl transition-all" title="Delete Chat">
                            <i class="bi bi-trash3 text-lg"></i>
                        </button>
                        <button type="button" onclick="toggleSidePanel()" class="hidden md:flex p-2 text-slate-500 hover:text-purple-600 hover:bg-slate-100 rounded-xl transition-all" title="Toggle File Manager & Info Panel">
                            <i class="bi bi-folder-symlink text-lg"></i>
                        </button>

                        <!-- Mobile Admin Tools Drawer Button -->
                        <button type="button" onclick="toggleMobileAdminDrawer()" class="md:hidden text-purple-700 bg-purple-50 hover:bg-purple-100 p-2 rounded-xl border border-purple-200 text-xs font-bold flex items-center gap-1" title="Files & Actions">
                            <i class="bi bi-paperclip text-sm"></i> Tools
                        </button>
                    </div>
                </div>

                <!-- Empty State (No Chat Selected) -->
                <div id="emptyAdminState" class="flex-1 flex flex-col items-center justify-center p-6 text-center">
                    <div class="w-16 h-16 sm:w-20 sm:h-20 bg-purple-50 text-purple-600 rounded-3xl flex items-center justify-center text-3xl sm:text-4xl mb-4 shadow-sm border border-purple-100">
                        <i class="bi bi-chat-left-dots-fill"></i>
                    </div>
                    <h3 class="text-base sm:text-lg font-bold text-slate-800">Select a Conversation</h3>
                    <p class="text-xs text-slate-500 max-w-sm mt-1">Choose a customer reservation to inspect history, send replies, or upload event documents.</p>
                </div>

                <!-- Chat History Box -->
                <div id="chatHistory" class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-4 chat-scroll touch-scroll hidden">
                    <!-- Messages rendered here -->
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

                <!-- Responsive Message Input Form -->
                <div id="chatInputArea" class="bg-white border-t border-slate-200 p-3 sm:p-4 z-10 hidden shrink-0">
                    <form id="messageForm" onsubmit="sendMessage(event)" class="flex items-end gap-2 sm:gap-3">
                        <!-- Attachment Trigger -->
                        <div class="relative">
                            <input type="file" id="fileInput" class="hidden" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.txt" onchange="handleFileSelect(event)">
                            <button type="button" onclick="document.getElementById('fileInput').click()" class="w-10 h-10 sm:w-11 sm:h-11 text-slate-500 hover:text-purple-600 hover:bg-purple-50 rounded-xl transition-all border border-slate-200 hover:border-purple-200 flex items-center justify-center shrink-0" title="Attach document or photo">
                                <i class="bi bi-paperclip text-lg"></i>
                            </button>
                        </div>

                        <!-- Textarea -->
                        <div class="flex-1 bg-slate-50 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-700 rounded-2xl px-3 py-2 focus-within:ring-2 focus-within:ring-purple-500 focus-within:border-transparent transition-all">
                            <textarea id="messageInput" rows="1" placeholder="Type official response..." class="w-full bg-transparent text-xs sm:text-sm text-slate-800 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none resize-none max-h-28" style="background-color: transparent !important;" onkeydown="handleKeyDown(event)"></textarea>
                        </div>

                        <!-- Send Reply Button -->
                        <button type="submit" id="sendBtn" class="w-10 h-10 sm:w-auto sm:px-5 sm:h-11 bg-purple-600 hover:bg-purple-700 active:scale-95 text-white font-bold text-xs rounded-xl sm:rounded-2xl flex items-center justify-center gap-2 transition-all shadow-md shadow-purple-200 shrink-0">
                            <span class="hidden sm:inline">Reply</span>
                            <i class="bi bi-send-fill text-xs sm:text-sm"></i>
                        </button>
                    </form>
                </div>
            </div>

            <!-- DESKTOP RIGHT SIDE PANEL: Admin Tools & File Manager -->
            <div id="sidePanel" class="w-72 bg-white border-l border-slate-200 p-4 flex flex-col gap-4 shrink-0 overflow-y-auto hidden">
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Linked Reservation Details</h3>
                    <div class="bg-slate-50 rounded-2xl p-4 border border-slate-200/80 space-y-3 text-xs">
                        <div>
                            <p class="text-[10px] font-bold text-purple-600 uppercase">Event Name</p>
                            <p id="sideEventName" class="font-extrabold text-slate-900 text-sm"></p>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
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
                            <p class="text-[10px] text-slate-400 font-semibold">Venue</p>
                            <p id="sideVenue" class="font-medium text-slate-700 line-clamp-2"></p>
                        </div>
                        <div class="pt-2 border-t border-slate-200 flex items-center justify-between">
                            <span class="text-[10px] font-bold text-slate-400 uppercase">Booking Link</span>
                            <a id="viewBookingLink" href="#" target="_blank" class="text-xs font-extrabold text-purple-600 hover:underline flex items-center gap-1">
                                View Full <i class="bi bi-box-arrow-up-right text-[10px]"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Admin Preset File Quick Action Tools -->
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Admin File Quick Actions</h3>
                    <p class="text-[11px] text-slate-500 mb-3">Select a document template to auto-fill response:</p>

                    <div class="grid grid-cols-2 gap-2 text-xs font-bold">
                        <button onclick="prefillTemplate('Contract')" class="p-3 bg-purple-50 hover:bg-purple-100 border border-purple-200 text-purple-800 rounded-xl flex flex-col items-center gap-1 transition-all text-center">
                            <i class="bi bi-file-earmark-text text-lg text-purple-600"></i>
                            <span class="text-[11px]">Contract</span>
                        </button>
                        <button onclick="prefillTemplate('Quotation')" class="p-3 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-800 rounded-xl flex flex-col items-center gap-1 transition-all text-center">
                            <i class="bi bi-receipt-cutoff text-lg text-emerald-600"></i>
                            <span class="text-[11px]">Quotation</span>
                        </button>
                        <button onclick="prefillTemplate('Menu')" class="p-3 bg-amber-50 hover:bg-amber-100 border border-amber-200 text-amber-800 rounded-xl flex flex-col items-center gap-1 transition-all text-center">
                            <i class="bi bi-journal-richtext text-lg text-amber-600"></i>
                            <span class="text-[11px]">Menu Selection</span>
                        </button>
                        <button onclick="prefillTemplate('Timeline')" class="p-3 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 text-indigo-800 rounded-xl flex flex-col items-center gap-1 transition-all text-center">
                            <i class="bi bi-clock-history text-lg text-indigo-600"></i>
                            <span class="text-[11px]">Timeline</span>
                        </button>
                        <button onclick="prefillTemplate('Payment')" class="p-3 bg-rose-50 hover:bg-rose-100 border border-rose-200 text-rose-800 rounded-xl flex flex-col items-center gap-1 transition-all text-center col-span-2">
                            <i class="bi bi-credit-card-2-front text-lg text-rose-600"></i>
                            <span class="text-[11px]">Payment Instructions</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MOBILE ADMIN TOOLS DRAWER (Slide-up sheet for small screens) -->
    <div id="mobileAdminDrawer" class="fixed inset-0 z-[90] bg-slate-950/60 backdrop-blur-xs hidden md:hidden flex flex-col justify-end" onclick="toggleMobileAdminDrawer()">
        <div class="bg-white rounded-t-3xl p-6 max-h-[85vh] overflow-y-auto space-y-4" onclick="event.stopPropagation()">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-sm font-extrabold text-slate-900">Admin Document & Quick Tools</h3>
                <button type="button" onclick="toggleMobileAdminDrawer()" class="text-slate-400 hover:text-slate-600 p-1">
                    <i class="bi bi-x-lg text-lg"></i>
                </button>
            </div>
            <div class="grid grid-cols-2 gap-2 text-xs font-bold">
                <button onclick="prefillTemplate('Contract'); toggleMobileAdminDrawer();" class="p-3 bg-purple-50 hover:bg-purple-100 border border-purple-200 text-purple-800 rounded-xl flex flex-col items-center gap-1 text-center">
                    <i class="bi bi-file-earmark-text text-xl text-purple-600"></i>
                    <span>Send Contract</span>
                </button>
                <button onclick="prefillTemplate('Quotation'); toggleMobileAdminDrawer();" class="p-3 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-800 rounded-xl flex flex-col items-center gap-1 text-center">
                    <i class="bi bi-receipt-cutoff text-xl text-emerald-600"></i>
                    <span>Quotation</span>
                </button>
                <button onclick="prefillTemplate('Menu'); toggleMobileAdminDrawer();" class="p-3 bg-amber-50 hover:bg-amber-100 border border-amber-200 text-amber-800 rounded-xl flex flex-col items-center gap-1 text-center">
                    <i class="bi bi-journal-richtext text-xl text-amber-600"></i>
                    <span>Menu Selection</span>
                </button>
                <button onclick="prefillTemplate('Timeline'); toggleMobileAdminDrawer();" class="p-3 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 text-indigo-800 rounded-xl flex flex-col items-center gap-1 text-center">
                    <i class="bi bi-clock-history text-xl text-indigo-600"></i>
                    <span>Event Timeline</span>
                </button>
                <button onclick="prefillTemplate('Payment'); toggleMobileAdminDrawer();" class="p-3 bg-rose-50 hover:bg-rose-100 border border-rose-200 text-rose-800 rounded-xl flex flex-col items-center gap-1 text-center col-span-2">
                    <i class="bi bi-credit-card-2-front text-xl text-rose-600"></i>
                    <span>Payment Instructions</span>
                </button>
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
        let activeStatusFilter = 'all';
        let searchQuery = '';
        let allMessages = [];
        let maxMessageId = 0;
        let isPolling = false;
        let pollTimer = null;
        let convPollTimer = null;
        let selectedFile = null;
        let conversationsList = [];

        document.addEventListener('DOMContentLoaded', () => {
            loadConversations();

            // Intelligent InfinityFree traffic management:
            // Pause polling when tab is hidden, resume immediately when user returns
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) {
                    if (currentBookingId) loadMessages(false);
                    loadConversations();
                }
            });

            // Relaxed background directory refresh (every 15s) to save server CPU
            convPollTimer = setInterval(() => {
                if (!document.hidden) {
                    loadConversations(true);
                }
            }, 15000);
        });

        // 1. Fetch Conversations Directory
        async function loadConversations(isBackground = false) {
            try {
                const url = `auth/chat_api.php?action=fetch_conversations&status=${activeStatusFilter}&search=${encodeURIComponent(searchQuery)}`;
                const res = await fetch(url);
                const data = await res.json();

                if (data.success || data.status === 'success') {
                    conversationsList = data.conversations || [];
                    renderConversationsList(conversationsList);

                    if (!isBackground && currentBookingId > 0) {
                        selectConversation(currentBookingId);
                    }
                } else {
                    renderConversationsList([]);
                    console.warn("Could not load conversations:", data.message || data.error);
                }
            } catch (err) {
                console.error("Error loading conversations:", err);
                renderConversationsList([]);
            }
        }

        // 2. Render Directory List
        function renderConversationsList(list) {
            const container = document.getElementById('convList');

            if (list.length === 0) {
                container.innerHTML = `
                    <div class="p-8 text-center text-slate-400 text-xs">
                        <i class="bi bi-chat-square-x text-2xl inline-block mb-1"></i>
                        <p class="font-bold">No conversations found</p>
                        <p class="text-[10px] mt-0.5">Try altering your search or status filter.</p>
                    </div>`;
                return;
            }

            let html = '';
            list.forEach(c => {
                const isActive = (c.booking_id === currentBookingId);
                const initials = (c.customer_name || 'C').substring(0, 1).toUpperCase();
                
                let badgeClass = 'bg-slate-100 text-slate-600';
                if (c.status === 'pending') badgeClass = 'bg-amber-100 text-amber-800';
                else if (c.status === 'confirmed' || c.status === 'on_progress') badgeClass = 'bg-emerald-100 text-emerald-800';
                else if (c.status === 'completed') badgeClass = 'bg-blue-100 text-blue-800';
                else if (c.status === 'cancelled') badgeClass = 'bg-rose-100 text-rose-800';

                html += `
                    <div onclick="selectConversation(${c.booking_id})" class="conv-item p-3.5 sm:p-4 border-b border-slate-100 cursor-pointer hover:bg-slate-50 transition-all ${isActive ? 'active' : ''}" id="conv-item-${c.booking_id}">
                        <div class="flex items-start gap-3">
                            <div class="relative shrink-0">
                                <div class="w-10 h-10 rounded-2xl bg-purple-600 text-white font-black flex items-center justify-center text-sm uppercase shadow-sm">
                                    ${initials}
                                </div>
                                <span class="w-3 h-3 rounded-full bg-emerald-500 border-2 border-white absolute -bottom-0.5 -right-0.5"></span>
                            </div>
                            <div class="flex-1 overflow-hidden">
                                <div class="flex items-center justify-between gap-1">
                                    <h4 class="text-xs font-extrabold text-slate-900 truncate">${escapeHtml(c.customer_name || 'Customer')}</h4>
                                    <span class="text-[10px] text-slate-400 font-semibold shrink-0">${c.time_ago}</span>
                                </div>
                                <div class="flex items-center gap-1.5 mt-1">
                                    <span class="text-[9px] font-extrabold px-1.5 py-0.5 rounded-full ${badgeClass} uppercase">#${c.booking_id} • ${c.status}</span>
                                    <span class="text-[11px] font-semibold text-slate-500 truncate">${escapeHtml(c.event_name)}</span>
                                </div>
                                <div class="flex items-center justify-between gap-2 mt-1.5">
                                    <p class="text-xs text-slate-500 truncate font-medium flex-1">${escapeHtml(c.snippet)}</p>
                                    ${c.unread_count > 0 ? `<span class="w-5 h-5 rounded-full bg-rose-500 text-white font-black text-[10px] flex items-center justify-center shrink-0 shadow-2xs">${c.unread_count}</span>` : ''}
                                </div>
                            </div>
                        </div>
                    </div>`;
            });

            container.innerHTML = html;
        }

        // 3. Select Conversation (Responsive View Switcher)
        function selectConversation(bookingId) {
            currentBookingId = parseInt(bookingId);
            allMessages = [];
            maxMessageId = 0;

            document.querySelectorAll('.conv-item').forEach(el => el.classList.remove('active'));
            const activeEl = document.getElementById(`conv-item-${bookingId}`);
            if (activeEl) activeEl.classList.add('active');

            // Responsive behavior: hide left column on mobile screen when conversation is selected
            if (window.innerWidth < 768) {
                document.getElementById('convCol').classList.add('hidden');
                document.getElementById('chatCol').classList.remove('hidden');
                document.getElementById('chatCol').classList.add('flex');
            }

            document.getElementById('emptyAdminState').classList.add('hidden');
            document.getElementById('chatHeader').classList.remove('hidden');
            document.getElementById('chatHistory').classList.remove('hidden');
            document.getElementById('chatInputArea').classList.remove('hidden');

            loadMessages(true);

            if (pollTimer) clearInterval(pollTimer);
            // Adaptive 4-second poll interval to prevent traffic pile-ups on InfinityFree
            pollTimer = setInterval(() => {
                if (!document.hidden) {
                    loadMessages(false);
                }
            }, 4000);
        }

        // Mobile back to conversation list view
        function showConvListOnMobile() {
            document.getElementById('convCol').classList.remove('hidden');
            document.getElementById('chatCol').classList.add('hidden');
            document.getElementById('chatCol').classList.remove('flex');
        }

        // 4. Load & Render Active Messages (Zero-Waste Delta Polling)
        async function loadMessages(forceFull = false) {
            if (!currentBookingId || isPolling) return;

            isPolling = true;
            try {
                const afterParam = forceFull ? 0 : maxMessageId;
                const res = await fetch(`auth/chat_api.php?action=fetch_messages&booking_id=${currentBookingId}&after_id=${afterParam}`);
                const data = await res.json();

                if (!data.success && data.status !== 'success') {
                    if (forceFull) console.warn(data.message || data.error || 'Failed to load conversation');
                    return;
                }

                if (forceFull && data.booking) {
                    updateAdminHeader(data.booking);
                }

                if (data.remaining_unread !== undefined) {
                    updateSidebarChatBadge(data.remaining_unread);
                    const activeItemBadge = document.querySelector(`#conv-item-${currentBookingId} span.bg-rose-500`);
                    if (activeItemBadge) activeItemBadge.remove();
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
            const badge = sidebar.querySelector('a[href="admin_messages.php"] span.rounded-full');
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

        // Update Active Headers
        function updateAdminHeader(b) {
            document.getElementById('activeAvatar').textContent = (b.customer_name || 'C').substring(0, 1).toUpperCase();
            document.getElementById('activeCustomerName').textContent = b.customer_name || 'Customer';
            document.getElementById('activeBookingBadge').textContent = `Booking #${b.booking_id}`;
            document.getElementById('activeEventName').textContent = b.event_name;
            document.getElementById('activeEventDate').textContent = b.formatted_event_date;
            
            const pill = document.getElementById('activeStatusPill');
            pill.textContent = b.status;
            pill.className = 'font-bold uppercase text-[9px] sm:text-[10px] px-2 py-0.5 rounded-full ';
            if (b.status === 'pending') pill.className += 'bg-amber-100 text-amber-800 border border-amber-200';
            else if (b.status === 'confirmed' || b.status === 'on_progress') pill.className += 'bg-emerald-100 text-emerald-800 border border-emerald-200';
            else if (b.status === 'completed') pill.className += 'bg-blue-100 text-blue-800 border border-blue-200';
            else pill.className += 'bg-rose-100 text-rose-800 border border-rose-200';

            // Right Panel
            document.getElementById('sideEventName').textContent = b.event_name;
            document.getElementById('sideEventDate').textContent = b.formatted_event_date;
            document.getElementById('sidePax').textContent = `${b.pax_count} Guests`;
            document.getElementById('sideVenue').textContent = b.venue_location || b.venue_address || 'Not specified';
            document.getElementById('viewBookingLink').href = `view_booking.php?id=${b.booking_id}`;
        }

        // Render Messages
        function renderMessages(messages) {
            const container = document.getElementById('chatHistory');

            if (messages.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-12">
                        <div class="inline-block p-4 rounded-full bg-purple-50 text-purple-600 mb-2">
                            <i class="bi bi-chat-text text-2xl"></i>
                        </div>
                        <p class="text-xs font-bold text-slate-700">No message history yet</p>
                        <p class="text-[11px] text-slate-400 max-w-xs mx-auto mt-1">Send a welcome message or attach event documents to begin.</p>
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
                    ? '<span class="text-purple-300 font-bold" title="Read by customer"><i class="bi bi-check2-all text-xs"></i> Read</span>' 
                    : '<span class="text-purple-200/80 font-medium" title="Sent"><i class="bi bi-check2 text-xs"></i> Sent</span>';

                let senderLabel = 'Customer';
                if (isMine) {
                    const isStaffUser = '<?= strtolower($_SESSION['role'] ?? '') ?>' === 'staff';
                    senderLabel = isStaffUser ? 'You (Staff)' : 'You (Admin)';
                } else if (m.sender_role === 'staff') {
                    senderLabel = `${escapeHtml(m.sender_name || 'Staff')} (Staff)`;
                } else if (m.sender_role === 'admin') {
                    senderLabel = `${escapeHtml(m.sender_name || 'Admin')} (Admin)`;
                } else {
                    senderLabel = escapeHtml(m.sender_name || 'Customer');
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

        // Quick Admin Template Messages
        function prefillTemplate(type) {
            const input = document.getElementById('messageInput');
            if (type === 'Contract') {
                input.value = "Hello! Please find attached your official Catering Service Contract for your review and record.";
            } else if (type === 'Quotation') {
                input.value = "Greetings! Here is your detailed breakdown and Quotation for the requested event package.";
            } else if (type === 'Menu') {
                input.value = "Dear Customer, here is your customized Menu Selection and dish details for your reservation.";
            } else if (type === 'Timeline') {
                input.value = "Attached is the proposed Event Timeline and catering service schedule for your event.";
            } else if (type === 'Payment') {
                input.value = "Payment Instructions: You can settle payments via GCash or Bank Transfer. Please upload your payment receipt here for verification.";
            }
            input.focus();
            document.getElementById('fileInput').click();
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
                alert('Please select a customer conversation first');
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
                    loadConversations();
                } else {
                    alert(data.message || data.error || 'Failed to send message');
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

        // Filter Handlers
        function setStatusFilter(status) {
            activeStatusFilter = status;
            ['all', 'pending', 'approved', 'completed', 'cancelled', 'archived'].forEach(s => {
                const btn = document.getElementById(`filter-${s}`);
                if (btn) {
                    if (s === status) {
                        btn.className = 'px-3 py-1.5 rounded-xl transition-all bg-purple-600 text-white shadow-2xs';
                    } else {
                        btn.className = 'px-3 py-1.5 rounded-xl transition-all text-slate-600 hover:bg-slate-100';
                    }
                }
            });
            loadConversations();
            
            // Toggle Archive/Unarchive buttons based on tab
            if(status === 'archived') {
                document.getElementById('btn-archive').classList.add('hidden');
                document.getElementById('btn-unarchive').classList.remove('hidden');
            } else {
                document.getElementById('btn-archive').classList.remove('hidden');
                document.getElementById('btn-unarchive').classList.add('hidden');
            }
        }

        function filterConversations() {
            searchQuery = document.getElementById('searchConv').value.trim();
            loadConversations();
        }

        function toggleSidePanel() {
            document.getElementById('sidePanel').classList.toggle('hidden');
        }

        async function archiveCurrentChat() {
            if (!activeBookingId) return;
            const result = await Swal.fire({
                title: 'Archive Chat?',
                text: "This chat will be hidden from the active list.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#9333ea',
                cancelButtonColor: '#94a3b8',
                confirmButtonText: 'Yes, Archive'
            });
            
            if (result.isConfirmed) {
                try {
                    const fd = new FormData();
                    fd.append('action', 'archive_chat');
                    fd.append('booking_id', activeBookingId);
                    
                    const res = await fetch('auth/chat_api.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    
                    if (data.success) {
                        Swal.fire({
                            toast: true, position: 'top-end', icon: 'success',
                            title: 'Chat Archived', showConfirmButton: false, timer: 2000
                        });
                        // Reset view
                        document.getElementById('chatHistory').classList.add('hidden');
                        document.getElementById('chatInputArea').classList.add('hidden');
                        document.getElementById('chatHeader').classList.add('hidden');
                        document.getElementById('emptyAdminState').classList.remove('hidden');
                        activeBookingId = null;
                        loadConversations();
                    } else {
                        Swal.fire('Error', data.error || 'Failed to archive chat', 'error');
                    }
                } catch (e) {
                    Swal.fire('Error', 'Network error', 'error');
                }
            }
        }

        async function unarchiveCurrentChat() {
            if (!activeBookingId) return;
            const result = await Swal.fire({
                title: 'Restore Chat?',
                text: "This will move the chat back to the active inbox.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#16a34a',
                cancelButtonColor: '#94a3b8',
                confirmButtonText: 'Yes, Restore'
            });
            
            if (result.isConfirmed) {
                try {
                    const fd = new FormData();
                    fd.append('action', 'unarchive_chat');
                    fd.append('booking_id', activeBookingId);
                    
                    const res = await fetch('auth/chat_api.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    
                    if (data.success) {
                        Swal.fire({
                            toast: true, position: 'top-end', icon: 'success',
                            title: 'Chat Restored', showConfirmButton: false, timer: 2000
                        });
                        // Reset view
                        document.getElementById('chatHistory').classList.add('hidden');
                        document.getElementById('chatInputArea').classList.add('hidden');
                        document.getElementById('chatHeader').classList.add('hidden');
                        document.getElementById('emptyAdminState').classList.remove('hidden');
                        activeBookingId = null;
                        loadConversations();
                    } else {
                        Swal.fire('Error', data.error || 'Failed to restore chat', 'error');
                    }
                } catch (e) {
                    Swal.fire('Error', 'Network error', 'error');
                }
            }
        }

        async function deleteCurrentChat() {
            if (!activeBookingId) return;
            const result = await Swal.fire({
                title: 'Permanently Delete Chat?',
                text: "This will erase all messages for this booking. This action cannot be undone!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#94a3b8',
                confirmButtonText: 'Yes, Delete'
            });
            
            if (result.isConfirmed) {
                try {
                    const fd = new FormData();
                    fd.append('action', 'delete_chat');
                    fd.append('booking_id', activeBookingId);
                    
                    const res = await fetch('auth/chat_api.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    
                    if (data.success) {
                        Swal.fire({
                            toast: true, position: 'top-end', icon: 'success',
                            title: 'Chat Deleted', showConfirmButton: false, timer: 2000
                        });
                        // Reset view
                        document.getElementById('chatHistory').classList.add('hidden');
                        document.getElementById('chatInputArea').classList.add('hidden');
                        document.getElementById('chatHeader').classList.add('hidden');
                        document.getElementById('emptyAdminState').classList.remove('hidden');
                        activeBookingId = null;
                        loadConversations();
                    } else {
                        Swal.fire('Error', data.error || 'Failed to delete chat', 'error');
                    }
                } catch (e) {
                    Swal.fire('Error', 'Network error', 'error');
                }
            }
        }

        function toggleMobileAdminDrawer() {
            document.getElementById('mobileAdminDrawer').classList.toggle('hidden');
        }

        // Image Modal Lightbox
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
