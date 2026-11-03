<?php
$pkg = $_GET['pkg'] ?? 'A';

// Menu Options that are shared
$menuChoices = [
    'Beef' => [
        'Deconstructed Kare Kare',
        'Beef Salisbury',
        'Garlic Pepper Steak',
        'Beef Stir Fry w/ Snow Peas'
    ],
    'Pork' => [
        'Pork Imbutido',
        'Pork Hamonado',
        'Pork Adobo',
        'Pork Menudo'
    ],
    'Pancit' => [
        'Pancit Malabon',
        'Pancit Bihon',
        'Pancit Canton',
        'Pancit Lomi'
    ],
    'Veggies' => [
        'Chopsuey',
        'Mixed Veggies with Quail Egg',
        'Pinakbet',
        'Mixed Vegetable'
    ],
    'Dessert' => [
        'Mango Tapioca',
        'Buko Fruit Salad',
        'Mixed Fresh Fruits',
        'Coffee Jelly'
    ],
    'Chicken' => [
        'Chicken Satay w/ Peanut Sauce',
        'Roasted Garlic Lemon Chicken',
        'Garlic Buttered Chicken',
        'Pinoy Style Fried Chicken'
    ],
    'Fish' => [
        'Spicy Mixed Seafoods',
        'Red Snapper w/ French Sauce',
        'Spicy Buttered Prawn',
        'Shrimp Verde'
    ]
];

$packages = [
    'A' => [
        'title' => 'PACKAGE A',
        'prices' => '45k 50pax | 65k 100pax | 85k 150pax | 105k 200pax',
        'head' => 'Php450/HEAD',
        'pattern' => ['1 Beef or Pork', '1 Veggies', '1 Pasta', '1 Dessert'],
        'categories' => ['BEEF' => $menuChoices['Beef'], 'PORK' => $menuChoices['Pork'], 'PANCIT' => $menuChoices['Pancit'], 'VEGGIES' => $menuChoices['Veggies'], 'DESSERT' => $menuChoices['Dessert']]
    ],
    'B' => [
        'title' => 'PACKAGE B',
        'prices' => '62k 50pax | 87k 100pax | 118k 150pax | 139k 200pax',
        'head' => 'Php650/HEAD',
        'pattern' => ['1 Beef', '1 Pork', '1 Veggies', '1 Pasta', '1 Dessert'],
        'categories' => ['BEEF' => $menuChoices['Beef'], 'PORK' => $menuChoices['Pork'], 'PANCIT' => $menuChoices['Pancit'], 'VEGGIES' => $menuChoices['Veggies'], 'DESSERT' => $menuChoices['Dessert']]
    ],
    'C' => [
        'title' => 'PACKAGE C',
        'prices' => '73k 50pax | 107k 100pax | 142k 150pax | 169k 200pax',
        'head' => 'Php850/HEAD',
        'pattern' => ['1 Beef', '1 Pork', '1 Chicken or Fish', '1 Veggies', '1 Pasta', '1 Dessert'],
        'categories' => ['BEEF' => $menuChoices['Beef'], 'PORK' => $menuChoices['Pork'], 'CHICKEN' => $menuChoices['Chicken'], 'FISH' => $menuChoices['Fish'], 'PANCIT' => $menuChoices['Pancit'], 'VEGGIES' => $menuChoices['Veggies'], 'DESSERT' => $menuChoices['Dessert']]
    ],
    'D' => [
        'title' => 'PACKAGE D',
        'prices' => '81k 50pax | 120k 100pax | 154k 150pax | 186k 200pax',
        'head' => 'Php1000/HEAD',
        'pattern' => ['1 Beef', '1 Pork', '1 Chicken', '1 Fish', '1 Veggies', '1 Pasta', '1 Dessert'],
        'categories' => ['BEEF' => $menuChoices['Beef'], 'PORK' => $menuChoices['Pork'], 'CHICKEN' => $menuChoices['Chicken'], 'FISH' => $menuChoices['Fish'], 'PANCIT' => $menuChoices['Pancit'], 'VEGGIES' => $menuChoices['Veggies'], 'DESSERT' => $menuChoices['Dessert']]
    ]
];

$data = $packages[$pkg] ?? $packages['A'];

$services = [
    'Modern | Elegant reception setup/design',
    'Round table suitable for 10 seaters applied with floor length tablecloth',
    'Covered chairs with ribbon',
    'Well-designed glass centerpieces with elegant flowers',
    'Complete Buffet setup with elegant Roll Top Chafing Dish',
    'Framed table number',
    'Colored motif table napkins',
    'Complete set of dinnerware & utensils',
    'Professional | Trained | Uniformed Server & Waiters'
];
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
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800;900&display=swap');
        body { font-family: 'Montserrat', sans-serif; background-color: #f3f4f6; }
        
        #pdf-container {
            width: 8.5in;
            min-height: 11in;
            background: #fffdf9;
            margin: 0 auto;
            padding: 0.4in;
            position: relative;
        }

        .header-bg {
            background-color: #f3ede2;
            text-align: center;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .title-text {
            color: #b78a6f;
            font-size: 56px;
            font-weight: 900;
            line-height: 1;
            letter-spacing: 2px;
            margin: 5px 0;
        }

        .subtitle {
            color: #333;
            font-size: 20px;
            font-weight: 700;
            letter-spacing: 4px;
        }
        
        #loading {
            position: fixed; inset: 0; background: white; z-index: 9999;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
        }
        .spinner { border: 4px solid #f3f3f3; border-top: 4px solid #b78a6f; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>

    <div id="loading">
        <div class="spinner mb-4"></div>
        <h2 class="text-lg font-black text-[#b78a6f] tracking-widest uppercase">Generating PDF...</h2>
        <p class="text-sm text-gray-500 font-medium mt-2">Please wait while your document is being prepared.</p>
    </div>

    <!-- The content to be converted to PDF -->
    <div id="pdf-container">
        
        <div class="header-bg">
            <p class="text-[10px] font-bold tracking-[0.3em] text-gray-600 mb-0">JEMMERIFIC CATERING SERVICES</p>
            <h1 class="title-text"><?php echo $data['title']; ?></h1>
            <p class="subtitle uppercase">For All Occasion</p>
        </div>

        <div class="text-center mb-4">
            <h2 class="text-lg font-black text-[#b78a6f] uppercase tracking-widest border-b-2 border-[#b78a6f]/30 inline-block pb-0.5 mb-2">Complete Catering Services</h2>
            <ul class="text-[11px] font-semibold text-gray-800 leading-snug space-y-0.5 italic">
                <?php foreach($services as $s): ?>
                    <li><?php echo $s; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="text-center bg-[#b78a6f] text-white py-2 rounded-lg mb-4 shadow-sm">
            <h3 class="text-lg font-bold tracking-widest"><?php echo $data['prices']; ?></h3>
        </div>

        <div class="grid grid-cols-2 gap-6 mb-4">
            <div class="text-center bg-[#fcf8f2] p-3 rounded-xl border border-[#f3ede2]">
                <h3 class="text-lg font-black text-[#d09d7d] mb-0"><?php echo $data['head']; ?></h3>
                <p class="text-[11px] font-bold text-[#d09d7d]/80 mb-2">(Minimum of 50pax)</p>
                <div class="text-[11px] font-semibold text-gray-700 italic space-y-0.5">
                    <p>Steamed Rice</p>
                    <p>Purified Drinking Water</p>
                    <p>Red Iced Tea</p>
                </div>
            </div>
            
            <div class="text-center bg-[#fcf8f2] p-3 rounded-xl border border-[#f3ede2]">
                <h3 class="text-md font-black text-[#d09d7d] uppercase tracking-widest mb-1.5">Menu Pattern</h3>
                <div class="text-[11px] font-bold text-gray-700 italic space-y-0.5">
                    <?php foreach($data['pattern'] as $pat): ?>
                        <p><?php echo $pat; ?></p>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="text-center mb-3">
            <h2 class="text-lg font-black text-[#b78a6f] uppercase tracking-widest border-b-2 border-[#b78a6f]/30 inline-block pb-0.5">Menu Choices</h2>
        </div>

        <div class="grid <?php echo count($data['categories']) > 5 ? 'grid-cols-4' : 'grid-cols-3'; ?> gap-x-2 gap-y-4">
            <?php foreach($data['categories'] as $catName => $items): ?>
                <div class="text-center">
                    <h4 class="text-[13px] font-black tracking-widest text-gray-900 uppercase underline decoration-2 underline-offset-4 mb-1.5"><?php echo $catName; ?></h4>
                    <ul class="text-[10px] font-semibold italic text-gray-800 space-y-0.5">
                        <?php foreach($items as $item): ?>
                            <li><?php echo $item; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Occasion List & Image at the bottom -->
        <div class="mt-4 flex gap-4 items-center border-t-2 border-[#f3ede2] pt-4 absolute bottom-[0.4in] left-[0.4in] right-[0.4in]">
            <div class="w-1/3 text-center pl-2">
                <h3 class="text-xl font-bold italic mb-2" style="font-family: serif;">For All Occasion</h3>
                <ul class="text-[12px] font-semibold text-gray-800 space-y-0.5">
                    <li>Event Parties</li>
                    <li>Baptismal</li>
                    <li>Birthday Parties</li>
                    <li>Corporate Parties</li>
                    <li>And Many More...</li>
                </ul>
            </div>
            <div class="w-2/3 h-36 bg-gray-200 rounded-xl overflow-hidden shadow-md">
                <img src="uploads/gallery/wedding.png" class="w-full h-full object-cover" alt="Event Setup">
            </div>
        </div>

    </div>

    <script>
        window.onload = function() {
            setTimeout(() => {
                var element = document.getElementById('pdf-container');
                var opt = {
                    margin:       0,
                    filename:     'All_Occasion_Package_<?php echo $pkg; ?>.pdf',
                    image:        { type: 'jpeg', quality: 1.0 },
                    html2canvas:  { scale: 2, useCORS: true, logging: false },
                    jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' }
                };
                
                html2pdf().set(opt).from(element).outputPdf('bloburl').then(function(pdfUrl) {
                    document.getElementById('loading').style.display = 'none';
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
