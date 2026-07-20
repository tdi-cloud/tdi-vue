<?php

// Listahan ng lahat ng customizable na image slots sa public homepage.
// Ang 'default' ay ang kasalukuyang hardcoded na larawan bago ito na-customize
// ng isang superadmin. Kapag walang override sa `site_images` table, ito ang
// gagamitin.
return [
    'navbar_logo' => [
        'label' => 'Navbar Logo (TESDA Seal)',
        'section' => 'Navbar',
        'default' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/e/ef/TESDA_Seal.svg/1280px-TESDA_Seal.svg.png',
    ],

    'hero_slide_1' => [
        'label' => 'Hero Slideshow — Slide 1',
        'section' => 'Hero',
        'default' => '/storage/hero/hrmo.jpg',
    ],
    'hero_slide_2' => [
        'label' => 'Hero Slideshow — Slide 2',
        'section' => 'Hero',
        'default' => '/storage/hero/hrmo1.jpeg',
    ],
    'hero_slide_3' => [
        'label' => 'Hero Slideshow — Slide 3',
        'section' => 'Hero',
        'default' => 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=1600&q=80',
    ],

    'about_photo' => [
        'label' => 'About Section Photo',
        'section' => 'About',
        'default' => '/storage/hero/cpsc2.JPG',
    ],

    'panel_classroom' => [
        'label' => 'Training Panel — Classroom Training',
        'section' => 'Training Panels',
        'default' => '/storage/hero/cpsc.JPG',
    ],
    'panel_coaching' => [
        'label' => 'Training Panel — Coaching & Mentoring',
        'section' => 'Training Panels',
        'default' => 'https://images.unsplash.com/photo-1491975474562-1f4e30bc9468?w=600&q=80',
    ],
    'panel_online' => [
        'label' => 'Training Panel — Online & Blended',
        'section' => 'Training Panels',
        'default' => 'https://images.unsplash.com/photo-1506784983877-45594efa4cbe?w=600&q=80',
    ],
    'panel_leadership' => [
        'label' => 'Training Panel — Leadership Programs',
        'section' => 'Training Panels',
        'default' => '/storage/hero/ethno.jpeg',
    ],

    'fstp_photo_1' => [
        'label' => 'FSTP Nominees Abroad — Photo 1',
        'section' => 'FSTP Nominees Abroad',
        'default' => 'https://c-korea.vn/wp-content/uploads/2024/10/TUK-3-768x392.jpg',
    ],
    'fstp_photo_2' => [
        'label' => 'FSTP Nominees Abroad — Photo 2',
        'section' => 'FSTP Nominees Abroad',
        'default' => 'https://images.unsplash.com/photo-1556761175-5973dc0f32e7?w=500&q=80',
    ],
    'fstp_photo_3' => [
        'label' => 'FSTP Nominees Abroad — Photo 3',
        'section' => 'FSTP Nominees Abroad',
        'default' => 'https://marvel-b1-cdn.bc0a.com/f00000000290162/images.ctfassets.net/2htm8llflwdx/59339XFWyVJlC424edZsWo/4a683e5a909cdefbc1fe8278c047d1c6/SL_Benefits_of_Studying_Abroad_-_SEO.jpg?fit=thumb',
    ],
    'fstp_photo_4' => [
        'label' => 'FSTP Nominees Abroad — Photo 4',
        'section' => 'FSTP Nominees Abroad',
        'default' => 'https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?w=500&q=80',
    ],

    'sponsor_logo_jica' => [
        'label' => 'Sponsor Logo — JICA',
        'section' => 'Sponsor Logos',
        'default' => '/storage/sponsors/jica.png',
    ],
    'sponsor_logo_koica' => [
        'label' => 'Sponsor Logo — KOICA',
        'section' => 'Sponsor Logos',
        'default' => '/storage/sponsors/koica.png',
    ],
    'sponsor_logo_mtcp' => [
        'label' => 'Sponsor Logo — MTCP',
        'section' => 'Sponsor Logos',
        'default' => '/storage/sponsors/mtcp.png',
    ],
    'sponsor_logo_scp' => [
        'label' => 'Sponsor Logo — SCP',
        'section' => 'Sponsor Logos',
        'default' => '/storage/sponsors/scp.png',
    ],
    'sponsor_logo_tica' => [
        'label' => 'Sponsor Logo — TICA',
        'section' => 'Sponsor Logos',
        'default' => '/storage/sponsors/tica.png',
    ],
    'sponsor_logo_itec' => [
        'label' => 'Sponsor Logo — ITEC',
        'section' => 'Sponsor Logos',
        'default' => '/storage/sponsors/itec.png',
    ],
];
