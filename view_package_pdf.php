<?php
$pkg = $_GET['pkg'] ?? 'A';

// Package Data
$packages = [
    'A' => [
        'title' => 'WEDDING CATERING PACKAGE A',
        'price' => '100PAX 89K | 150PAX 112K',
        'image' => 'uploads/gallery/wedding.png',
        'payment' => [
            '1️⃣ Downpayment: ₱10,000',
            '2️⃣ Final Payment (1 month prior)'
        ],
        'freebies' => ['Cake', 'Pair of Doves', 'Wine for couple', 'Registration Table Setup', 'Free Food Tasting', 'Free Use of Red Carpet'],
        'catering' => [
            'head' => 'Php550 FOR ADDITIONAL HEAD (Minimum of 50pax)',
            'items' => [
                'Modern | Elegant reception setup/design',
                'Round table suitable for 10 seaters or applied with floor length tablecloth',
                'Covered chairs with elegant ribbon',
                'Well-designed glass centerpieces with elegant flowers',
                'Elegant Themed Backdrop',
                'Tiffany chairs for the couple',
                'Complete Buffet setup with elegant Roll Top Chafing Dish',
                'Framed table number',
                'Colored motif table napkins',
                'Complete set of dinnerware & utensils',
                'Professional | Trained | Uniformed Server & Waiters',
                '3 Layered Edible Cake'
            ]
        ],
        'menu' => [
            'inclusions' => ['Steamed Rice', 'Purified Drinking Water', 'Iced Tea', 'Chicken Hot Soup'],
            'pattern' => '1 Beef | 1 Chicken or 1 Fish | 1 Veggies | 1 Pasta | 1 Dessert | 1 Soup',
            'sets' => [
                [
                    'title' => 'Set 1',
                    'dishes' => [
                        'Sirloin beef strips in soy and sweet sauce w/ freshly sautéed bottom mushroom',
                        'Korean chicken fillet in modern teriyaki sauce',
                        'Sautéed mixed vegies w/ boiled quail egg on top, together w/ aroma of sesame oil',
                        'Al dente pasta in creamy garlic white sauce'
                    ],
                    'dessert' => ['Freshly grated coconut w/ pandan jelly in sweet and creamy sauce']
                ],
                [
                    'title' => 'Set 2',
                    'dishes' => [
                        'Slow cooked beef chunk in aromatic thick sauce',
                        'Seasoned coated fish fillet in flavorful sweet and sour sauce',
                        'Filipino style sautéed mixed vegies in thick oyster sauce',
                        'Sweet & Filipino style red pasta'
                    ],
                    'dessert' => ['Bittersweet coffee gelatin w/ creamy coffee syrup']
                ]
            ]
        ]
    ],
    'B' => [
        'title' => 'WEDDING CATERING PACKAGE B',
        'price' => '50PAX 79K | 100PAX 111K | 150PAX 144K',
        'image' => 'uploads/gallery/wedding.png',
        'payment' => [
            '1️⃣ Downpayment: ₱10,000',
            '2️⃣ Final Payment (1 month prior)'
        ],
        'freebies' => ['Cake', 'Pair of Doves', 'Wine for couple', 'Registration Table Setup', 'Free Food Tasting', 'Free Use of Red Carpet'],
        'catering' => [
            'head' => 'Php750 FOR ADDITIONAL HEAD (Minimum of 50pax)',
            'items' => [
                'Tiffany chairs for VIP only',
                'Modern | Elegant reception setup/design',
                'Round table suitable for 10 seaters or applied with floor length tablecloth',
                'Covered chairs with elegant ribbon',
                'Well-designed glass centerpieces with elegant flowers',
                'Elegant Theme Backdrop',
                'Tiffany chairs for the couple',
                'Complete Buffet setup with elegant Roll Top Chafing Dish',
                'Framed table number',
                'Colored motif table napkins',
                'Complete set of dinnerware & utensils',
                'Professional | Trained | Uniformed Server & Waiters',
                '3 Layered Edible Cake'
            ]
        ],
        'menu' => [
            'inclusions' => ['Steamed Rice', 'Purified Drinking Water', 'Iced Tea', 'Chicken Hot Soup'],
            'pattern' => '1 Beef | 1 Chicken | 1 Fish | 1 Veggies | 1 Pasta | 1 Dessert | 1 Soup',
            'sets' => [
                [
                    'title' => 'Set 1',
                    'dishes' => [
                        'Sirloin beef strips in soy and sweet sauce w/ freshly sautéed bottom mushroom',
                        'Korean chicken fillet in modern teriyaki sauce',
                        'Sautéed mixed vegies w/ boiled quail egg on top, together w/ aroma of sesame oil',
                        'Al dente pasta in creamy garlic white sauce'
                    ],
                    'dessert' => ['Freshly grated coconut w/ pandan jelly in sweet and creamy sauce']
                ],
                [
                    'title' => 'Set 2',
                    'dishes' => [
                        'Slow cooked beef chunk in aromatic thick sauce',
                        'Seasoned coated fish fillet in flavorful sweet and sour sauce',
                        'Filipino style sautéed mixed vegies in thick oyster sauce',
                        'Sweet & Filipino style red pasta'
                    ],
                    'dessert' => ['Bittersweet coffee gelatin w/ creamy coffee syrup']
                ]
            ]
        ]
    ],
    'C' => [
        'title' => 'WEDDING CATERING PACKAGE C',
        'price' => '50PAX 105K | 100PAX 147K | 150PAX 185K',
        'image' => 'uploads/gallery/wedding.png',
        'payment' => [
            '1️⃣ Downpayment: ₱10,000',
            '2️⃣ Final Payment (1 month prior)'
        ],
        'freebies' => ['Cake', 'Pair of Doves', 'Wine for couple', 'Registration Table Setup', 'Free Food Tasting', 'Free Use of Red Carpet'],
        'catering' => [
            'head' => 'Php1000 FOR ADDITIONAL HEAD (Minimum of 50pax)',
            'items' => [
                'All Tiffany chairs',
                'Modern | Elegant reception setup/design',
                'Round table suitable for 10 seaters applied with floor length tablecloth',
                'Well-designed glass centerpieces with elegant flowers',
                'Elegant Theme Backdrop',
                'Couple\'s couch (Freebie)',
                'Complete Buffet setup with elegant Roll Top Chafing Dish',
                'Framed table number',
                'Colored motif table napkins',
                'Complete set of dinnerware & utensils',
                'Professional | Trained | Uniformed Server & Waiters',
                '3 Fondant Layered Edible Cake'
            ]
        ],
        'menu' => [
            'inclusions' => ['Steamed Rice', 'Purified Drinking Water', 'Iced Tea', 'Chicken Hot Soup'],
            'pattern' => '1 Beef | 1 Pork | 1 Chicken | 1 Fish | 1 Veggies | 1 Pasta | 1 Dessert | 1 Appetizer | 1 Soup',
            'categories' => [
                'Beef' => ['Chinese Style Braised Beef', 'Mongolian Beef', 'Rose Beef w/ Rosemary Sauce', 'Spinach and Feta Stuffed Flank Steak', 'Skirt Steak w/ Golden Butter'],
                'Pork' => ['American Baby Back Ribs', 'Maple Savory Porkloin', 'Spicy Lechon Kawali', 'Garlic Pork Stir Fry', 'Braised Pork Ribs'],
                'Chicken' => ['Chicken Fricassee', 'Pinoy Chicken Inasal', 'Chicken Marsala', 'Chicken Breast w/ Boursin Cheese', 'Creamy Chicken Casserole'],
                'Fish' => ['Garlic Parmesan Shrimp', 'Spicy Buttered Prawn', 'Red Snapper w/ French Sauce', 'Sesame Seared Tuna', 'Shrimp Verde'],
                'Vegetable' => ['Broccoli w/ 3 Kinds of Mushroom', 'Cauliflower Melanese', 'Baked Capsicum w/ Sage Leaves', 'Easy Creamy Corn', 'Italian Oven Roasted Veggies'],
                'Pasta' => ['Grilled Steak Carbonara', 'Seafood Vongole', 'Shrimp Scampi w/ Chorizo', 'Baked Tomato Pasta', 'Spring Creamy Fettuccine'],
                'Dessert' => ['Espresso Panna Cotta', 'Mango Graham', 'Gateau Basque', 'Mini Cheesecakes', 'Mango Cheesecakes'],
                'Soup' => ['Celery Soup', 'Clam Chowder', 'Creamy Sweet Potato', 'Cauliflower Cheddar Soup'],
                'Appetizer' => ['Jackstone', 'Capnese', 'Shrimp Avocado', 'Egg Canapes']
            ]
        ]
    ]
];

$data = $packages[$pkg] ?? $packages['A'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $data['title']; ?> PDF</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
        
        /* PDF specific styling to ensure it fits nicely on a standard page */
        #pdf-container {
            width: 8.5in;
            min-height: 11in;
            background: white;
            margin: 0 auto;
            padding: 0.5in;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        #loading {
            position: fixed; inset: 0; background: white; z-index: 9999;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
        }
        .spinner { border: 4px solid #f3f3f3; border-top: 4px solid #a855f7; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>

    <div id="loading">
        <div class="spinner mb-4"></div>
        <h2 class="text-lg font-black text-purple-600 tracking-widest uppercase">Generating PDF...</h2>
        <p class="text-sm text-gray-500 font-medium mt-2">Please wait while your document is being prepared.</p>
    </div>

    <!-- The content to be converted to PDF -->
    <div id="pdf-container">
        
        <!-- Header Image -->
        <div class="w-full h-48 bg-slate-100 rounded-2xl overflow-hidden mb-6 relative">
            <img src="<?php echo $data['image']; ?>" class="w-full h-full object-cover" crossorigin="anonymous" alt="Wedding Package">
            <div class="absolute inset-0 bg-black/50 flex flex-col items-center justify-center text-center p-4">
                <h1 class="text-3xl font-black text-white uppercase tracking-widest"><?php echo $data['title']; ?></h1>
                <p class="text-purple-200 font-bold text-sm tracking-widest uppercase mt-2 bg-purple-900/40 px-3 py-1 rounded-full border border-purple-500/30"><?php echo $data['price']; ?></p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-8 mb-6">
            <!-- Payment & Freebies -->
            <div>
                <h2 class="text-lg font-black text-gray-800 uppercase tracking-wider mb-3 flex items-center gap-2"><span class="w-2 h-6 bg-purple-500 rounded-full"></span> Payment Process</h2>
                <ul class="text-sm font-semibold text-gray-600 space-y-1 mb-6">
                    <?php foreach($data['payment'] as $p): ?>
                        <li><?php echo $p; ?></li>
                    <?php endforeach; ?>
                </ul>

                <h2 class="text-lg font-black text-gray-800 uppercase tracking-wider mb-3 flex items-center gap-2"><span class="w-2 h-6 bg-pink-500 rounded-full"></span> Freebies</h2>
                <ul class="text-sm font-medium text-gray-600 list-disc list-inside space-y-1">
                    <?php foreach($data['freebies'] as $f): ?>
                        <li><?php echo $f; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Complete Services -->
            <div>
                <h2 class="text-lg font-black text-gray-800 uppercase tracking-wider mb-2 flex items-center gap-2"><span class="w-2 h-6 bg-amber-500 rounded-full"></span> Complete Services</h2>
                <p class="text-xs font-bold text-purple-600 mb-3 bg-purple-50 inline-block px-2 py-1 rounded"><?php echo $data['catering']['head']; ?></p>
                <ul class="text-[11px] font-medium text-gray-600 list-disc list-inside space-y-1">
                    <?php foreach($data['catering']['items'] as $item): ?>
                        <li><?php echo $item; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- Menu Section -->
        <h2 class="text-lg font-black text-gray-800 uppercase tracking-wider mb-3 flex items-center gap-2 border-b-2 border-gray-100 pb-2"><span class="w-2 h-6 bg-orange-500 rounded-full"></span> Catering Menu</h2>
        
        <div class="flex gap-4 mb-4">
            <div class="bg-gray-50 p-3 rounded-lg border border-gray-200 flex-1">
                <p class="text-xs font-black uppercase text-gray-800 mb-2">Inclusions</p>
                <ul class="text-[11px] font-semibold text-gray-600 list-decimal list-inside">
                    <?php foreach($data['menu']['inclusions'] as $inc): ?>
                        <li><?php echo $inc; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="bg-purple-50 p-3 rounded-lg border border-purple-100 flex-1">
                <p class="text-xs font-black uppercase text-purple-800 mb-2">Menu Pattern</p>
                <p class="text-[11px] font-bold text-purple-600 leading-relaxed"><?php echo $data['menu']['pattern']; ?></p>
            </div>
        </div>

        <?php if(isset($data['menu']['sets'])): ?>
            <div class="grid grid-cols-2 gap-4">
                <?php foreach($data['menu']['sets'] as $set): ?>
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h3 class="text-sm font-black text-purple-600 uppercase mb-2">🔸 <?php echo $set['title']; ?></h3>
                        <p class="text-[10px] font-black text-gray-400 uppercase mb-1">Dishes</p>
                        <ul class="text-[11px] font-medium text-gray-700 list-decimal list-inside space-y-1 mb-3">
                            <?php foreach($set['dishes'] as $dish): ?>
                                <li><?php echo $dish; ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="text-[10px] font-black text-gray-400 uppercase mb-1">Dessert</p>
                        <ul class="text-[11px] font-medium text-gray-700 list-decimal list-inside">
                            <?php foreach($set['dessert'] as $des): ?>
                                <li><?php echo $des; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif(isset($data['menu']['categories'])): ?>
            <div class="grid grid-cols-3 gap-x-4 gap-y-3">
                <?php foreach($data['menu']['categories'] as $catName => $catItems): ?>
                    <div>
                        <h3 class="text-[11px] font-black text-purple-600 uppercase mb-1 border-b border-purple-100 pb-1">🔸 <?php echo $catName; ?></h3>
                        <ul class="text-[10px] font-medium text-gray-700 list-disc list-inside space-y-0.5">
                            <?php foreach($catItems as $item): ?>
                                <li><?php echo $item; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Areas we serve & Contact -->
        <div class="mt-8 border-t-2 border-gray-100 pt-6 grid grid-cols-2 gap-8">
            <div>
                <h3 class="text-sm font-black text-gray-800 uppercase mb-2">Areas We Serve</h3>
                <ul class="text-xs font-medium text-gray-600 space-y-1">
                    <li>📍 Metro Manila Wide & Antipolo</li>
                    <li>📍 Cavite Wide (including Tagaytay)</li>
                    <li>📍 Laguna (San Pedro to Los Baños)</li>
                    <li>📍 Batangas (Caleruega, Santo Tomas, Tanauan & Lipa)</li>
                </ul>
            </div>
            <div class="bg-gray-900 text-white p-4 rounded-xl">
                <h3 class="text-xs font-black text-purple-300 uppercase mb-2 tracking-widest">Contact Us</h3>
                <p class="text-[11px] font-medium mb-1">📞 TNT - 0953 501 8946</p>
                <p class="text-[11px] font-medium mb-1">📞 SMART - 0928 466 0578</p>
                <p class="text-[11px] font-medium mb-2">📞 GLOBE - 0977 385 5525</p>
                <p class="text-[10px] text-gray-400">📍 3rd Floor, LBV Building, Molino Blvd, Bacoor Cavite</p>
            </div>
        </div>
    </div>

    <script>
        window.onload = function() {
            // Give images a moment to load fully before capturing
            setTimeout(() => {
                var element = document.getElementById('pdf-container');
                var opt = {
                    margin:       0,
                    filename:     'Wedding_Package_<?php echo $pkg; ?>.pdf',
                    image:        { type: 'jpeg', quality: 1.0 },
                    html2canvas:  { scale: 2, useCORS: true, logging: false },
                    jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' }
                };
                
                html2pdf().set(opt).from(element).outputPdf('bloburl').then(function(pdfUrl) {
                    // Hide the loading screen
                    document.getElementById('loading').style.display = 'none';
                    
                    // Create an iframe to display the PDF full screen
                    const iframe = document.createElement('iframe');
                    iframe.style.position = 'fixed';
                    iframe.style.top = '0';
                    iframe.style.left = '0';
                    iframe.style.width = '100vw';
                    iframe.style.height = '100vh';
                    iframe.style.border = 'none';
                    iframe.style.zIndex = '99999';
                    iframe.src = pdfUrl;
                    
                    document.body.innerHTML = '';
                    document.body.appendChild(iframe);
                });
            }, 500);
        };
    </script>
</body>
</html>
