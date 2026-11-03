<?php
require_once 'includes/admin_auth_check.php';
require_once 'db_config.php';

$user_name = $_SESSION['full_name'] ?? 'System Administrator';

// Search and Category Filter Logic
$search = $_GET['search'] ?? '';
$cat_filter = $_GET['category'] ?? '';

$where = " WHERE 1=1";
if($search) $where .= " AND mi.item_name LIKE '%" . $conn->real_escape_string($search) . "%'";
if($cat_filter) $where .= " AND mc.category_id = '" . $conn->real_escape_string($cat_filter) . "'";

// Main Table Query
$menu_res = $conn->query("SELECT mi.*, mc.category_name 
                          FROM menu_items mi 
                          JOIN menu_categories mc ON mi.category_id = mc.category_id 
                          $where 
                          ORDER BY mc.category_name ASC, mi.item_name ASC");

// Categories for Filter and Modal
$categories_res = $conn->query("SELECT * FROM menu_categories ORDER BY category_name ASC");
$cat_array = [];
while($row = $categories_res->fetch_assoc()) {
    $cat_array[] = $row;
}

// Fetch Packages for the new configuration section
$packages_res = $conn->query("SELECT * FROM packages ORDER BY package_id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Management | JEMmerific</title>
    <link rel="icon" type="image/png" href="uploads/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; letter-spacing: -0.01em; background-color: #FAFBFF; }
        .sidebar-link { transition: all 0.2s ease-in-out; }
        .sidebar-link:hover { background-color: #e5e7eb; color: #9b7cd1; padding-left: 1.5rem; }
        .active-link { background: linear-gradient(90deg, #d4cef3 0%, transparent 100%); color: #7c3aed; font-weight: 700; border-right: 4px solid #7c3aed; }
        .glass-card { background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(10px); }
        .modal { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); z-index: 150; }
        body.modal-active { overflow: hidden; }
        .modal-overlay { background-color: rgba(0, 0, 0, 0.5); backdrop-filter: blur(4px); }
        .dish-img-preview { transition: transform 0.5s ease; width: 100%; height: 100%; aspect-ratio: 1 / 1; object-fit: cover; display: block; }
        tr:hover .dish-img-preview { transform: scale(1.08); }
        .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #E2E8F0; border-radius: 10px; }
    </style>
</head>
<body class="flex min-h-screen">

    <?php require_once 'includes/admin_sidebar.php'; ?>

    <main class="flex-1 lg:ml-64 p-4 sm:p-6 md:p-8 transition-all">
        <header class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 sm:gap-6 mb-8 sm:mb-12">
            <div class="flex items-start gap-3.5">
                <button onclick="toggleSidebar()" class="lg:hidden mt-0.5 p-2.5 min-h-[44px] min-w-[44px] flex items-center justify-center bg-white border border-gray-100 rounded-xl shadow-sm text-gray-600 hover:bg-gray-50 active:scale-95 transition-all">
                    <i class="bi bi-list text-2xl"></i>
                </button>
                <div>
                    <h2 class="text-3xl sm:text-4xl font-black text-gray-900 tracking-tighter">Menu</h2>
                    <p class="text-xs sm:text-sm text-gray-400 font-semibold mt-1">Manage your dish details, recipes, and images.</p>
                </div>
            </div>
            <button onclick="toggleModal('addDishModal')" class="w-full sm:w-auto min-h-[44px] bg-gray-900 text-white px-6 sm:px-8 py-3.5 sm:py-4 rounded-2xl text-[10px] font-black uppercase tracking-widest flex items-center justify-center gap-2 hover:bg-purple-600 transition shadow-xl shadow-gray-100 active:scale-[0.99]">
                <i class="bi bi-plus-lg text-sm"></i> Add New Dish
            </button>
        </header>

        <!-- Package Configuration Section -->
        <div class="mb-10">
            <div class="flex items-center gap-2 mb-6">
                <span class="w-1.5 h-4 bg-purple-600 rounded-full"></span>
                <h3 class="text-xs font-black text-slate-900 uppercase tracking-widest">Package Tier Configuration</h3>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6">
                <?php while ($pkg = $packages_res->fetch_assoc()): ?>
                <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100/80 relative hover:shadow-md transition-all group flex flex-col justify-between">
                    <div>
                        <div class="flex justify-between items-center mb-4">
                            <div class="w-9 h-9 rounded-xl bg-purple-600 text-white flex items-center justify-center font-extrabold text-xs shadow-sm shadow-purple-200">
                                <?= htmlspecialchars($pkg['package_id']) ?>
                            </div>
                            <button onclick='openEditPackageModal(<?= json_encode($pkg) ?>)' class="p-2 text-slate-400 hover:text-purple-600 hover:bg-purple-50 rounded-xl transition" title="Edit Package Tier">
                                <i class="bi bi-pencil-square text-base"></i>
                            </button>
                        </div>
                        <h4 class="font-extrabold text-slate-900 text-base mb-1"><?= htmlspecialchars($pkg['package_name']) ?></h4>
                        <p class="text-xs text-slate-400 font-medium leading-relaxed mb-4 line-clamp-2"><?= htmlspecialchars($pkg['description'] ?? 'No description set.') ?></p>
                    </div>

                    <div class="pt-4 border-t border-slate-100 space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">Rate / PAX</span>
                            <span class="text-xs font-black text-purple-700 bg-purple-50 px-2.5 py-1 rounded-lg border border-purple-100">
                                ₱<?= number_format($pkg['price_per_pax'], 2) ?>
                            </span>
                        </div>
                        <div>
                            <span class="text-[9px] font-extrabold text-slate-400 uppercase tracking-wider block mb-1.5">Category Choice Limits</span>
                            <div class="grid grid-cols-4 gap-1 bg-slate-50 p-2 rounded-xl border border-slate-100/70 text-center">
                                <div><p class="text-[8px] font-bold text-slate-400 uppercase">Main</p><p class="text-xs font-black text-slate-800"><?= $pkg['main_course_limit'] ?? 0 ?></p></div>
                                <div><p class="text-[8px] font-bold text-slate-400 uppercase">Pasta</p><p class="text-xs font-black text-slate-800"><?= $pkg['pasta_limit'] ?? 0 ?></p></div>
                                <div><p class="text-[8px] font-bold text-slate-400 uppercase">Veg</p><p class="text-xs font-black text-slate-800"><?= $pkg['veggies_limit'] ?? 0 ?></p></div>
                                <div><p class="text-[8px] font-bold text-slate-400 uppercase">Dessert</p><p class="text-xs font-black text-slate-800"><?= $pkg['dessert_limit'] ?? 0 ?></p></div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>

        <div class="glass-card p-4 rounded-[2rem] mb-10 shadow-sm flex items-center gap-4">
            <form action="" method="GET" class="flex flex-col sm:flex-row flex-1 gap-3 sm:gap-4 w-full">
                <div class="relative flex-1">
                    <i class="bi bi-search absolute left-5 top-3.5 text-gray-400"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search for a dish..." 
                           class="w-full pl-14 pr-6 py-3.5 bg-gray-50/50 border-none rounded-2xl text-sm font-medium focus:ring-2 focus:ring-purple-500/20 min-h-[44px]">
                </div>
                <select name="category" onchange="this.form.submit()" class="bg-gray-50/50 border-none rounded-2xl px-6 py-3.5 text-sm font-bold text-gray-600 focus:ring-2 focus:ring-purple-500/20 min-h-[44px]">
                    <option value="">All Categories</option>
                    <?php foreach($cat_array as $cat): ?>
                        <option value="<?= $cat['category_id'] ?>" <?= $cat_filter == $cat['category_id'] ? 'selected' : '' ?>>
                            <?= $cat['category_name'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <div class="bg-white rounded-[2.5rem] overflow-hidden shadow-sm border border-slate-50">
            <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-gray-50/50">
                    <tr class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">
                        <th class="px-8 py-6">Dish & Description</th>
                        <th class="px-8 py-6">Category</th>
                        <th class="px-8 py-6 text-center">Status</th>
                        <th class="px-8 py-6 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php if($menu_res->num_rows > 0): ?>
                        <?php while($item = $menu_res->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50/30 transition group">
                            <td class="px-8 py-6 min-w-[260px] max-w-sm">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 shrink-0 aspect-square rounded-xl overflow-hidden shadow-sm border border-gray-100 bg-gray-50 relative flex items-center justify-center">
                                        <?php if(!empty($item['image_path'])): ?>
                                            <img src="<?= htmlspecialchars($item['image_path']) ?>" alt="<?= htmlspecialchars($item['item_name']) ?>" class="dish-img-preview w-full h-full aspect-square object-cover block">
                                        <?php else: ?>
                                            <div class="w-full h-full bg-purple-50 text-purple-600 flex items-center justify-center font-black text-xs uppercase">
                                                <?= substr($item['item_name'], 0, 1) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-bold text-gray-900 text-sm truncate"><?= htmlspecialchars($item['item_name']) ?></p>
                                        <p class="text-[10px] text-gray-400 font-medium line-clamp-2 leading-relaxed"><?= htmlspecialchars($item['description'] ?: 'No description provided.') ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-8 py-6">
                                <span class="px-3 py-1 bg-slate-100 rounded-lg text-[9px] font-black text-slate-500 uppercase tracking-wider">
                                    <?= htmlspecialchars($item['category_name']) ?>
                                </span>
                            </td>
                            <td class="px-8 py-6 text-center">
                                <button onclick="toggleAvailability(<?= $item['item_id'] ?>, <?= $item['is_available'] ?>)" 
                                        class="px-4 py-1.5 rounded-full text-[9px] font-black tracking-widest uppercase transition-all cursor-pointer
                                        <?= $item['is_available'] ? 'bg-green-100 text-green-600 border border-green-200' : 'bg-gray-100 text-gray-400 border border-gray-200' ?>">
                                    <i class="bi <?= $item['is_available'] ? 'bi-check-circle-fill' : 'bi-slash-circle' ?> mr-1"></i>
                                    <?= $item['is_available'] ? 'Available' : 'Unavailable' ?>
                                </button>
                            </td>
                            <td class="px-8 py-6 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <button onclick='viewRecipe(<?= json_encode($item) ?>)' class="p-2.5 text-gray-400 hover:text-purple-600 hover:bg-purple-50 rounded-xl transition" title="View Recipe">
                                        <i class="bi bi-journal-text"></i>
                                    </button>
                                    <button onclick='openEditModal(<?= json_encode($item) ?>)' class="p-2.5 text-blue-500 hover:bg-blue-50 rounded-xl transition" title="Edit">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <button onclick="confirmDelete(<?= $item['item_id'] ?>)" class="p-2.5 text-red-400 hover:bg-red-50 rounded-xl transition" title="Delete">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="py-20 text-center text-gray-300 text-xs font-bold uppercase tracking-widest">No items found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>
    </main>

    <!-- Add Dish Modal -->
    <div id="addDishModal" class="modal opacity-0 pointer-events-none fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="modal-overlay absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" onclick="toggleModal('addDishModal')"></div>
        <div class="modal-container bg-white w-full max-w-xl mx-auto rounded-3xl shadow-2xl z-50 overflow-hidden relative border border-slate-100">
            <!-- Header -->
            <div class="px-8 pt-7 pb-5 flex justify-between items-center border-b border-slate-100 bg-slate-50/50">
                <div class="flex items-center gap-3.5">
                    <div class="w-11 h-11 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center text-xl shadow-sm border border-purple-100/50">
                        <i class="bi bi-journal-plus"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-extrabold text-slate-900 tracking-tight">Add New Dish</h3>
                        <p class="text-xs text-slate-400 font-medium">Add a dish item to your catering inventory menu</p>
                    </div>
                </div>
                <button type="button" onclick="toggleModal('addDishModal')" class="text-slate-400 hover:text-slate-600 hover:bg-slate-100 p-2 rounded-xl transition">
                    <i class="bi bi-x-lg text-sm"></i>
                </button>
            </div>

            <!-- Body -->
            <form onsubmit="confirmAdd(event)" action="auth/add_menu_item.php" method="POST" enctype="multipart/form-data" class="p-8 space-y-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-1">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                            <i class="bi bi-egg-fried text-purple-500"></i> Dish Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="item_name" required placeholder="e.g. Garlic Butter Shrimp" 
                            class="w-full px-4 py-3 bg-slate-50/70 border border-slate-200 rounded-xl text-sm font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 focus:bg-white transition-all placeholder:text-slate-400">
                    </div>

                    <div class="sm:col-span-1">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                            <i class="bi bi-tag text-purple-500"></i> Category <span class="text-red-500">*</span>
                        </label>
                        <select name="category_id" required 
                            class="w-full px-4 py-3 bg-slate-50/70 border border-slate-200 rounded-xl text-sm font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 focus:bg-white transition-all">
                            <option value="" disabled selected>Select category</option>
                            <?php foreach($cat_array as $c): ?>
                                <option value="<?= $c['category_id'] ?>"><?= htmlspecialchars($c['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                        <i class="bi bi-card-text text-purple-500"></i> Short Description
                    </label>
                    <textarea name="description" rows="2" placeholder="Brief appetizer or dish summary for customer menu display..." 
                        class="w-full px-4 py-3 bg-slate-50/70 border border-slate-200 rounded-xl text-sm font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 focus:bg-white transition-all resize-none placeholder:text-slate-400"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                        <i class="bi bi-list-check text-purple-500"></i> Recipe & Preparation Notes
                    </label>
                    <textarea name="recipe" rows="3" placeholder="Key ingredients, cooking steps, or allergy notices for staff..." 
                        class="w-full px-4 py-3 bg-slate-50/70 border border-slate-200 rounded-xl text-sm font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 focus:bg-white transition-all resize-none placeholder:text-slate-400"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                        <i class="bi bi-image text-purple-500"></i> Dish Photo
                    </label>
                    <div class="relative border-2 border-dashed border-slate-200 rounded-2xl p-4 text-center hover:border-purple-400 bg-slate-50/50 hover:bg-purple-50/20 transition-all group">
                        <input type="file" name="image" accept="image/*" onchange="previewDishImage(this, 'add_img_preview', 'add_img_preview_container')" 
                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                        <div class="flex flex-col items-center justify-center gap-1.5 py-1">
                            <div class="w-10 h-10 rounded-full bg-purple-50 text-purple-600 flex items-center justify-center group-hover:scale-110 transition-transform">
                                <i class="bi bi-cloud-arrow-up text-lg"></i>
                            </div>
                            <p class="text-xs font-bold text-slate-700"><span class="text-purple-600">Click to upload</span> or drag and drop</p>
                            <p class="text-[11px] text-slate-400 font-medium">PNG, JPG, or WEBP (Max 5MB)</p>
                        </div>
                    </div>
                    <!-- Live Preview Container -->
                    <div id="add_img_preview_container" class="hidden mt-3 flex items-center gap-3 p-2.5 bg-purple-50/50 rounded-xl border border-purple-100">
                        <div class="w-12 h-12 shrink-0 aspect-square rounded-xl overflow-hidden border border-purple-200 shadow-sm bg-white flex items-center justify-center">
                            <img id="add_img_preview" src="" class="w-full h-full aspect-square object-cover block">
                        </div>
                        <div class="flex-1 overflow-hidden">
                            <p class="text-xs font-bold text-slate-800 truncate">Selected Image</p>
                            <p class="text-[10px] text-purple-600 font-medium">Ready to upload</p>
                        </div>
                    </div>
                </div>

                <!-- Footer Buttons -->
                <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                    <button type="button" onclick="toggleModal('addDishModal')" 
                        class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-extrabold uppercase tracking-wider transition-all">
                        Cancel
                    </button>
                    <button type="submit" 
                        class="px-6 py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-extrabold uppercase tracking-wider shadow-md hover:shadow-purple-200 flex items-center gap-2 transition-all">
                        <i class="bi bi-plus-circle-fill"></i> Save Dish
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Dish Modal -->
    <div id="editDishModal" class="modal opacity-0 pointer-events-none fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="modal-overlay absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" onclick="toggleModal('editDishModal')"></div>
        <div class="modal-container bg-white w-full max-w-xl mx-auto rounded-3xl shadow-2xl z-50 overflow-hidden relative border border-slate-100">
            <!-- Header -->
            <div class="px-8 pt-7 pb-5 flex justify-between items-center border-b border-slate-100 bg-slate-50/50">
                <div class="flex items-center gap-3.5">
                    <div class="w-11 h-11 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl shadow-sm border border-blue-100/50">
                        <i class="bi bi-pencil-square"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-extrabold text-slate-900 tracking-tight">Edit Dish</h3>
                        <p class="text-xs text-slate-400 font-medium">Update item details, category, and recipe</p>
                    </div>
                </div>
                <button type="button" onclick="toggleModal('editDishModal')" class="text-slate-400 hover:text-slate-600 hover:bg-slate-100 p-2 rounded-xl transition">
                    <i class="bi bi-x-lg text-sm"></i>
                </button>
            </div>

            <!-- Body -->
            <form action="auth/edit_menu_item.php" method="POST" enctype="multipart/form-data" class="p-8 space-y-5">
                <input type="hidden" name="item_id" id="edit_id">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-1">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                            <i class="bi bi-egg-fried text-blue-500"></i> Dish Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="item_name" id="edit_name" required 
                            class="w-full px-4 py-3 bg-slate-50/70 border border-slate-200 rounded-xl text-sm font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 focus:bg-white transition-all">
                    </div>

                    <div class="sm:col-span-1">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                            <i class="bi bi-tag text-blue-500"></i> Category <span class="text-red-500">*</span>
                        </label>
                        <select name="category_id" id="edit_category" required 
                            class="w-full px-4 py-3 bg-slate-50/70 border border-slate-200 rounded-xl text-sm font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 focus:bg-white transition-all">
                            <?php foreach($cat_array as $c): ?>
                                <option value="<?= $c['category_id'] ?>"><?= htmlspecialchars($c['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                        <i class="bi bi-card-text text-blue-500"></i> Short Description
                    </label>
                    <textarea name="description" id="edit_description" rows="2" 
                        class="w-full px-4 py-3 bg-slate-50/70 border border-slate-200 rounded-xl text-sm font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 focus:bg-white transition-all resize-none"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                        <i class="bi bi-list-check text-blue-500"></i> Recipe & Preparation Notes
                    </label>
                    <textarea name="recipe" id="edit_recipe" rows="3" 
                        class="w-full px-4 py-3 bg-slate-50/70 border border-slate-200 rounded-xl text-sm font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 focus:bg-white transition-all resize-none"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                        <i class="bi bi-image text-blue-500"></i> Update Dish Photo <span class="text-slate-400 font-normal lowercase">(optional)</span>
                    </label>
                    <div class="relative border-2 border-dashed border-slate-200 rounded-2xl p-4 text-center hover:border-blue-400 bg-slate-50/50 hover:bg-blue-50/20 transition-all group">
                        <input type="file" name="image" accept="image/*" onchange="previewDishImage(this, 'edit_img_preview', 'edit_img_preview_container')" 
                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                        <div class="flex flex-col items-center justify-center gap-1.5 py-1">
                            <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center group-hover:scale-110 transition-transform">
                                <i class="bi bi-cloud-arrow-up text-lg"></i>
                            </div>
                            <p class="text-xs font-bold text-slate-700"><span class="text-blue-600">Click to upload new image</span> or drag and drop</p>
                            <p class="text-[11px] text-slate-400 font-medium">Leave unchanged to keep current photo</p>
                        </div>
                    </div>
                    <!-- Live Preview Container -->
                    <div id="edit_img_preview_container" class="hidden mt-3 flex items-center gap-3 p-2.5 bg-blue-50/50 rounded-xl border border-blue-100">
                        <div class="w-12 h-12 shrink-0 aspect-square rounded-xl overflow-hidden border border-blue-200 shadow-sm bg-white flex items-center justify-center">
                            <img id="edit_img_preview" src="" class="w-full h-full aspect-square object-cover block">
                        </div>
                        <div class="flex-1 overflow-hidden">
                            <p class="text-xs font-bold text-slate-800 truncate">Current / Selected Photo</p>
                            <p class="text-[10px] text-blue-600 font-medium">Dish image preview</p>
                        </div>
                    </div>
                </div>

                <!-- Footer Buttons -->
                <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                    <button type="button" onclick="toggleModal('editDishModal')" 
                        class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-extrabold uppercase tracking-wider transition-all">
                        Cancel
                    </button>
                    <button type="submit" 
                        class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-extrabold uppercase tracking-wider shadow-md hover:shadow-blue-200 flex items-center gap-2 transition-all">
                        <i class="bi bi-check-circle-fill"></i> Update Dish
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Package Modal -->
    <div id="editPackageModal" class="modal opacity-0 pointer-events-none fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="modal-overlay absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" onclick="toggleModal('editPackageModal')"></div>
        <div class="modal-container bg-white w-full max-w-lg mx-auto rounded-3xl shadow-2xl z-50 overflow-hidden relative border border-slate-100">
            <!-- Header -->
            <div class="px-8 pt-7 pb-5 flex justify-between items-center border-b border-slate-100 bg-slate-50/50">
                <div class="flex items-center gap-3.5">
                    <div class="w-11 h-11 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center text-xl shadow-sm border border-purple-100/50">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-extrabold text-slate-900 tracking-tight" id="pkg_title">Edit Package Tier</h3>
                        <p class="text-xs text-slate-400 font-medium">Configure pricing per pax, category limits, and inclusions</p>
                    </div>
                </div>
                <button type="button" onclick="toggleModal('editPackageModal')" class="text-slate-400 hover:text-slate-600 hover:bg-slate-100 p-2 rounded-xl transition">
                    <i class="bi bi-x-lg text-sm"></i>
                </button>
            </div>

            <!-- Body -->
            <form action="auth/edit_package.php" method="POST" class="p-8 space-y-5">
                <input type="hidden" name="package_id" id="pkg_id">

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                        <i class="bi bi-currency-dollar text-purple-500"></i> Price Per Pax (₱) <span class="text-red-500">*</span>
                    </label>
                    <input type="number" step="0.01" name="price_per_pax" id="pkg_price" required 
                        class="w-full px-4 py-3 bg-slate-50/70 border border-slate-200 rounded-xl text-sm font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 focus:bg-white transition-all">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                        <i class="bi bi-sliders text-purple-500"></i> Course Limits <span class="text-slate-400 font-normal lowercase">(max choices per category)</span>
                    </label>
                    <div class="grid grid-cols-4 gap-2.5 bg-slate-50/60 p-3 rounded-2xl border border-slate-100">
                        <div class="text-center">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Main</label>
                            <input type="number" name="main_course_limit" id="pkg_limit" required min="0"
                                class="w-full text-center px-2 py-2 bg-white border border-slate-200 rounded-lg text-sm font-extrabold text-slate-800 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500">
                        </div>
                        <div class="text-center">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Pasta</label>
                            <input type="number" name="pasta_limit" id="pkg_pasta_limit" required min="0"
                                class="w-full text-center px-2 py-2 bg-white border border-slate-200 rounded-lg text-sm font-extrabold text-slate-800 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500">
                        </div>
                        <div class="text-center">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Veggies</label>
                            <input type="number" name="veggies_limit" id="pkg_veggies_limit" required min="0"
                                class="w-full text-center px-2 py-2 bg-white border border-slate-200 rounded-lg text-sm font-extrabold text-slate-800 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500">
                        </div>
                        <div class="text-center">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Dessert</label>
                            <input type="number" name="dessert_limit" id="pkg_dessert_limit" required min="0"
                                class="w-full text-center px-2 py-2 bg-white border border-slate-200 rounded-lg text-sm font-extrabold text-slate-800 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                        <i class="bi bi-card-text text-purple-500"></i> Package Overview
                    </label>
                    <textarea name="description" id="pkg_desc" rows="2" placeholder="Short description of this tier..." 
                        class="w-full px-4 py-3 bg-slate-50/70 border border-slate-200 rounded-xl text-sm font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 focus:bg-white transition-all resize-none placeholder:text-slate-400"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                        <i class="bi bi-list-stars text-purple-500"></i> Detailed Inclusions
                    </label>
                    <textarea name="inclusions" id="pkg_inclusions" rows="3" placeholder="List items, equipment, waitstaff, or setup included..." 
                        class="w-full px-4 py-3 bg-slate-50/70 border border-slate-200 rounded-xl text-sm font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 focus:bg-white transition-all resize-none placeholder:text-slate-400"></textarea>
                </div>

                <!-- Footer Buttons -->
                <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                    <button type="button" onclick="toggleModal('editPackageModal')" 
                        class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-extrabold uppercase tracking-wider transition-all">
                        Cancel
                    </button>
                    <button type="submit" 
                        class="px-6 py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-extrabold uppercase tracking-wider shadow-md hover:shadow-purple-200 flex items-center gap-2 transition-all">
                        <i class="bi bi-check-circle-fill"></i> Save Package
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="recipeModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center">
        <div class="modal-overlay absolute w-full h-full" onclick="toggleModal('recipeModal')"></div>
        <div class="modal-container bg-white w-full max-w-2xl mx-auto rounded-[2.5rem] shadow-2xl z-50 overflow-hidden">
            <div id="recipeContent" class="py-10 px-12"></div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
            document.getElementById('sidebarOverlay').classList.toggle('hidden');
        }

        function toggleModal(id) {
            const m = document.getElementById(id);
            m.classList.toggle('opacity-0');
            m.classList.toggle('pointer-events-none');
            document.body.classList.toggle('modal-active');
        }

        function toggleAvailability(id, currentStatus) {
            const newStatus = currentStatus === 1 ? 0 : 1;
            window.location.href = `auth/toggle_availability.php?id=${id}&status=${newStatus}`;
        }

        function viewRecipe(item) {
            const container = document.getElementById('recipeContent');
            container.innerHTML = `
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <span class="px-3 py-1 bg-purple-50 text-purple-600 rounded-lg text-[9px] font-black uppercase tracking-widest mb-2 inline-block">${item.category_name}</span>
                        <h3 class="text-3xl font-black text-gray-900">${item.item_name}</h3>
                    </div>
                    <button onclick="toggleModal('recipeModal')" class="text-gray-400 hover:text-gray-900"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <img src="${item.image_path || 'https://via.placeholder.com/400x400?text=No+Image'}" class="w-full aspect-square object-cover rounded-[2rem] shadow-xl mb-4">
                    </div>
                    <div class="space-y-6">
                        <div>
                            <h4 class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-2">Description</h4>
                            <p class="text-sm text-gray-600 leading-relaxed">${item.description || 'No description available.'}</p>
                        </div>
                        <div>
                            <h4 class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-2">Instructions</h4>
                            <p class="text-sm text-gray-800 leading-relaxed font-semibold whitespace-pre-wrap">${item.recipe || 'No recipe recorded.'}</p>
                        </div>
                    </div>
                </div>`;
            toggleModal('recipeModal');
        }

        function confirmAdd(event) {
            event.preventDefault();
            const form = event.target;

            Swal.fire({
                title: 'Add new dish?',
                text: "Confirming will add this item to the catering menu inventory.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#7c3aed',
                cancelButtonColor: '#f3f4f6',
                confirmButtonText: 'Yes, add it',
                cancelButtonText: '<span style="color: #6b7280">Cancel</span>',
                customClass: {
                    popup: 'rounded-[2rem]',
                    confirmButton: 'rounded-xl px-8 py-3 text-xs font-black uppercase tracking-widest',
                    cancelButton: 'rounded-xl px-8 py-3 text-xs font-black uppercase tracking-widest'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        }

        function previewDishImage(input, previewImgId, containerId) {
            const previewContainer = document.getElementById(containerId);
            const previewImg = document.getElementById(previewImgId);
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    previewContainer.classList.remove('hidden');
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function openEditModal(item) {
            document.getElementById('edit_id').value = item.item_id;
            document.getElementById('edit_name').value = item.item_name;
            document.getElementById('edit_category').value = item.category_id;
            document.getElementById('edit_description').value = item.description || '';
            document.getElementById('edit_recipe').value = item.recipe || '';

            const previewImg = document.getElementById('edit_img_preview');
            const previewContainer = document.getElementById('edit_img_preview_container');
            if (item.image_path) {
                previewImg.src = item.image_path;
                previewContainer.classList.remove('hidden');
            } else {
                previewContainer.classList.add('hidden');
            }

            toggleModal('editDishModal');
        }

        function openEditPackageModal(pkg) {
            document.getElementById('pkg_id').value = pkg.package_id;
            document.getElementById('pkg_title').innerText = 'Edit ' + pkg.package_name;
            document.getElementById('pkg_price').value = pkg.price_per_pax;
            document.getElementById('pkg_limit').value = pkg.main_course_limit;
            document.getElementById('pkg_pasta_limit').value = pkg.pasta_limit;
            document.getElementById('pkg_veggies_limit').value = pkg.veggies_limit;
            document.getElementById('pkg_dessert_limit').value = pkg.dessert_limit;
            document.getElementById('pkg_desc').value = pkg.description || '';
            document.getElementById('pkg_inclusions').value = pkg.inclusions || '';
            toggleModal('editPackageModal');
        }

        function confirmDelete(id) {
            Swal.fire({
                title: 'Delete this dish?',
                text: "This will permanently remove the item from the menu.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#7c3aed',
                cancelButtonColor: '#f3f4f6',
                confirmButtonText: 'Delete Item',
                customClass: {
                    popup: 'rounded-[2rem]',
                    confirmButton: 'rounded-xl px-8 py-3 text-xs font-black uppercase tracking-widest',
                    cancelButton: 'rounded-xl px-8 py-3 text-xs font-black uppercase tracking-widest'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `auth/delete_menu_item.php?id=${id}`;
                }
            })
        }
    </script>
</body>
</html>
