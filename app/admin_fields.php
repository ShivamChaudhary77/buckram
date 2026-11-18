<?php
/**
 * Field map: which fields each page type, section type and the settings screen
 * show in the dashboard. Forms, saving and validation are all generated from it,
 * so adding a field is one line.
 *
 * Field keys:
 *   key, label, type, help, options (select), fields (group/repeater), item (repeater
 *   item name), itemLabel (field shown on a collapsed item), advanced (hidden under
 *   "More options"), max (soft character limit), default, placeholder, rows
 *
 * Types: text, textarea, inline (one rich-text paragraph), richtext, code (HTML),
 *   image, url, icon, select, checkbox, number, list (list of short texts),
 *   numbers (list of numbers), table, group, repeater, page (choose a product), json
 */

function fields_button(string $key, string $label, string $defaultClass, bool $iconAfter = true): array
{
    return [
        'key' => $key, 'label' => $label, 'type' => 'group', 'fields' => [
            ['key' => 'label', 'label' => 'Button text', 'type' => 'text', 'help' => 'Leave empty to hide the button.'],
            ['key' => 'href', 'label' => 'Links to', 'type' => 'url', 'placeholder' => '/contact.html'],
            ['key' => 'class', 'label' => 'Style', 'type' => 'select', 'options' => button_styles(), 'default' => $defaultClass, 'advanced' => true],
            ['key' => 'iconBefore', 'label' => 'Icon before the text', 'type' => 'icon', 'advanced' => true],
            ['key' => 'iconAfter', 'label' => 'Icon after the text', 'type' => 'icon', 'advanced' => true, 'default' => $iconAfter ? 'fas fa-arrow-right ms-2' : ''],
            ['key' => 'newTab', 'label' => 'Open in a new tab', 'type' => 'checkbox', 'advanced' => true],
        ],
    ];
}

function button_styles(): array
{
    return [
        'btn btn-navy'                                => 'Dark blue',
        'btn btn-navy mt-2'                           => 'Dark blue (with space above)',
        'btn btn-outline-navy'                        => 'Dark blue outline',
        'btn btn-gold btn-lg-custom'                  => 'Gold, large',
        'btn btn-gold btn-sm-custom'                  => 'Gold, small',
        'btn btn-outline-light-custom btn-lg-custom'  => 'White outline, large (for dark backgrounds)',
    ];
}

function field_bg(): array
{
    return ['key' => 'bg', 'label' => 'Background', 'type' => 'select', 'options' => [
        ''                     => 'White',
        'bg-soft'              => 'Light grey',
        'bg-navy'              => 'Dark blue',
        'bg-soft section-tight' => 'Light grey, less space',
    ]];
}

function fields_section_header(bool $withSubtitle = true): array
{
    $f = [
        ['key' => 'eyebrow', 'label' => 'Small label above the heading', 'type' => 'text'],
        ['key' => 'heading', 'label' => 'Heading', 'type' => 'text'],
    ];
    if ($withSubtitle) {
        $f[] = ['key' => 'subtitle', 'label' => 'Intro text under the heading', 'type' => 'inline'];
    }
    return $f;
}

function field_columns(string $default): array
{
    return ['key' => 'col', 'label' => 'Cards per row (on a computer)', 'type' => 'select', 'default' => $default, 'options' => [
        'col-lg-3 col-md-6' => '4 per row',
        'col-lg-3 col-sm-6' => '4 per row (2 on tablets)',
        'col-lg-4 col-md-6' => '3 per row',
        'col-lg-6'          => '2 per row',
    ]];
}

function fields_delays(): array
{
    return [
        ['key' => 'delayMode', 'label' => 'Animation timing', 'type' => 'select', 'advanced' => true, 'options' => [
            'row' => 'Cards appear one after another in each row', 'linear' => 'Each card a little later than the one before', 'custom' => 'Use the delays below',
        ]],
        ['key' => 'delays', 'label' => 'Animation delays (milliseconds, one per card)', 'type' => 'numbers', 'advanced' => true],
    ];
}

/** Section (block) types. */
function block_types(): array
{
    $section = [
        ['key' => 'label', 'label' => 'Name in the dashboard', 'type' => 'text', 'advanced' => true, 'help' => 'Only you see this. Helps you find the section.'],
    ];
    return [
        'page-hero' => [
            'label' => 'Top banner', 'icon' => 'fa-image', 'hint' => 'The page heading on a background picture, with the breadcrumb.',
            'fields' => [
                ['key' => 'heading', 'label' => 'Page heading (the main title of the page)', 'type' => 'text', 'help' => 'Google reads this as the page’s main heading (H1). Use it once per page.'],
                ['key' => 'eyebrow', 'label' => 'Small label above the heading', 'type' => 'text'],
                ['key' => 'icon', 'label' => 'Icon next to the label', 'type' => 'icon'],
                ['key' => 'intro', 'label' => 'Intro sentence', 'type' => 'inline'],
                ['key' => 'image', 'label' => 'Background picture', 'type' => 'image'],
                ['key' => 'breadcrumb', 'label' => 'Show the breadcrumb (Home / … / this page)', 'type' => 'checkbox', 'default' => true],
            ],
        ],
        'media-text' => [
            'label' => 'Picture with text', 'icon' => 'fa-columns', 'hint' => 'A picture or video on one side, text on the other.',
            'fields' => array_merge([
                ['key' => 'eyebrow', 'label' => 'Small label above the heading', 'type' => 'text'],
                ['key' => 'heading', 'label' => 'Heading', 'type' => 'text'],
                ['key' => 'html', 'label' => 'Text', 'type' => 'richtext'],
                ['key' => 'miniFeatures', 'label' => 'Small feature boxes', 'type' => 'repeater', 'item' => 'feature', 'itemLabel' => 'title', 'fields' => [
                    ['key' => 'icon', 'label' => 'Icon', 'type' => 'icon'],
                    ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
                    ['key' => 'text', 'label' => 'Text', 'type' => 'text'],
                ]],
                ['key' => 'checklist', 'label' => 'Tick list', 'type' => 'list', 'item' => 'point', 'rich' => true],
                ['key' => 'afterHtml', 'label' => 'Text after the list', 'type' => 'richtext'],
                fields_button('button', 'Button', 'btn btn-navy'),
                ['key' => 'image', 'label' => 'Picture', 'type' => 'image'],
                ['key' => 'imageAlt', 'label' => 'Picture description (for Google and screen readers)', 'type' => 'text', 'help' => 'Describe what is in the picture in a few words.'],
                ['key' => 'video', 'label' => 'Video address (instead of a picture)', 'type' => 'url', 'help' => 'Leave empty to show the picture.'],
                ['key' => 'textFirst', 'label' => 'Picture on the left, text on the right — text first on phones', 'type' => 'checkbox', 'advanced' => true],
                field_bg(),
                ['key' => 'frame', 'label' => 'Picture frame', 'type' => 'select', 'advanced' => true, 'options' => ['img-frame' => 'Framed', 'product-hero-img' => 'Product picture']],
                ['key' => 'featureCol', 'label' => 'Feature box width', 'type' => 'select', 'advanced' => true, 'options' => ['col-sm-6' => 'Two side by side', 'col-sm-12' => 'Full width']],
                ['key' => 'mediaCol', 'label' => 'Picture column (layout code)', 'type' => 'text', 'advanced' => true],
                ['key' => 'textCol', 'label' => 'Text column (layout code)', 'type' => 'text', 'advanced' => true],
                ['key' => 'mediaAos', 'label' => 'Picture animation', 'type' => 'select', 'advanced' => true, 'options' => animations()],
                ['key' => 'textAos', 'label' => 'Text animation', 'type' => 'select', 'advanced' => true, 'options' => animations()],
                ['key' => 'mediaDelay', 'label' => 'Picture animation delay (ms)', 'type' => 'number', 'advanced' => true],
                ['key' => 'textDelay', 'label' => 'Text animation delay (ms)', 'type' => 'number', 'advanced' => true],
            ], $section),
        ],
        'card-grid' => [
            'label' => 'Cards', 'icon' => 'fa-th', 'hint' => 'A heading with a grid of small cards (icon or photo, title, text).',
            'fields' => array_merge(fields_section_header(), [
                ['key' => 'items', 'label' => 'Cards', 'type' => 'repeater', 'item' => 'card', 'itemLabel' => 'title', 'fields' => [
                    ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
                    ['key' => 'text', 'label' => 'Text', 'type' => 'inline'],
                    ['key' => 'icon', 'label' => 'Icon', 'type' => 'icon'],
                    ['key' => 'image', 'label' => 'Photo (photo cards only)', 'type' => 'image'],
                    ['key' => 'imageAlt', 'label' => 'Photo description', 'type' => 'text'],
                ]],
                ['key' => 'card', 'label' => 'Card style', 'type' => 'select', 'options' => ['value' => 'Icon cards', 'variant' => 'Photo cards']],
                field_columns('col-lg-4 col-md-6'),
                ['key' => 'centered', 'label' => 'Centre the text in each card', 'type' => 'checkbox'],
                fields_button('link', 'Link under the cards', 'btn btn-outline-navy'),
                field_bg(),
                ['key' => 'dark', 'label' => 'White heading (for the dark blue background)', 'type' => 'checkbox', 'advanced' => true],
                ['key' => 'center', 'label' => 'Centre an incomplete last row', 'type' => 'checkbox', 'advanced' => true],
                ['key' => 'titleTag', 'label' => 'Card title size', 'type' => 'select', 'advanced' => true, 'options' => ['h3' => 'Normal', 'h4' => 'Smaller']],
            ], fields_delays(), $section),
        ],
        'spec-table' => [
            'label' => 'Table', 'icon' => 'fa-table', 'hint' => 'A specification or comparison table.',
            'fields' => array_merge(fields_section_header(), [
                ['key' => 'columns', 'label' => 'Column headings', 'type' => 'list', 'item' => 'column'],
                ['key' => 'rows', 'label' => 'Rows', 'type' => 'table'],
                ['key' => 'note', 'label' => 'Note under the table', 'type' => 'inline'],
                ['key' => 'style', 'label' => 'Table style', 'type' => 'select', 'options' => ['spec-table' => 'Specification table', 'compare-table' => 'Comparison table']],
                field_bg(),
            ], $section),
        ],
        'faq' => [
            'label' => 'Questions & answers', 'icon' => 'fa-question-circle', 'hint' => 'Frequently asked questions. Google can show these in search results.',
            'fields' => array_merge([
                ['key' => 'eyebrow', 'label' => 'Small label above the heading', 'type' => 'text'],
                ['key' => 'heading', 'label' => 'Heading', 'type' => 'text'],
                ['key' => 'intro', 'label' => 'Intro text', 'type' => 'inline'],
                fields_button('button', 'Button', 'btn btn-navy mt-2', false),
                ['key' => 'items', 'label' => 'Questions', 'type' => 'repeater', 'item' => 'question', 'itemLabel' => 'q', 'fields' => [
                    ['key' => 'q', 'label' => 'Question', 'type' => 'text'],
                    ['key' => 'a', 'label' => 'Answer', 'type' => 'inline', 'help' => 'Start with the direct answer, then add detail.'],
                ]],
                field_bg(),
                ['key' => 'accordionId', 'label' => 'Technical name of the list', 'type' => 'text', 'advanced' => true],
                ['key' => 'itemPrefix', 'label' => 'Technical name of each question', 'type' => 'text', 'advanced' => true],
            ], $section),
        ],
        'product-cards' => [
            'label' => 'Product cards', 'icon' => 'fa-boxes', 'hint' => 'Cards linking to product pages. Each card uses the product’s own card details.',
            'fields' => array_merge(fields_section_header(), [
                ['key' => 'items', 'label' => 'Products', 'type' => 'repeater', 'item' => 'product', 'itemLabel' => 'page', 'fields' => [
                    ['key' => 'page', 'label' => 'Product', 'type' => 'page'],
                    ['key' => 'summary', 'label' => 'Different text here (optional)', 'type' => 'text', 'help' => 'Leave empty to use the product’s own card text.'],
                    ['key' => 'title', 'label' => 'Different title here (optional)', 'type' => 'text', 'advanced' => true],
                    ['key' => 'tag', 'label' => 'Different label here (optional)', 'type' => 'text', 'advanced' => true],
                    ['key' => 'image', 'label' => 'Different picture here (optional)', 'type' => 'image', 'advanced' => true],
                    ['key' => 'imageAlt', 'label' => 'Different picture description (optional)', 'type' => 'text', 'advanced' => true],
                ]],
                field_columns('col-lg-4 col-md-6'),
                ['key' => 'promo', 'label' => 'Dark “talk to us” tile at the end', 'type' => 'group', 'fields' => [
                    ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'help' => 'Leave empty to hide the tile.'],
                    ['key' => 'text', 'label' => 'Text', 'type' => 'text'],
                    ['key' => 'icon', 'label' => 'Icon', 'type' => 'icon', 'default' => 'fas fa-comments'],
                    fields_button('button', 'Button', 'btn btn-gold btn-sm-custom', false),
                ]],
                fields_button('link', 'Link under the cards', 'btn btn-outline-navy'),
                field_bg(),
                ['key' => 'center', 'label' => 'Centre an incomplete last row', 'type' => 'checkbox', 'advanced' => true],
            ], fields_delays(), $section),
        ],
        'cta' => [
            'label' => 'Call to action', 'icon' => 'fa-bullhorn', 'hint' => 'The dark banner asking visitors to get in touch.',
            'fields' => array_merge([
                ['key' => 'heading', 'label' => 'Heading', 'type' => 'text'],
                ['key' => 'text', 'label' => 'Text', 'type' => 'inline'],
                ['key' => 'buttons', 'label' => 'Buttons', 'type' => 'repeater', 'item' => 'button', 'itemLabel' => 'label', 'fields' => fields_button('', 'Button', 'btn btn-gold btn-lg-custom', false)['fields']],
            ], $section),
        ],
        'html' => [
            'label' => 'Custom HTML', 'icon' => 'fa-code', 'hint' => 'A section with its own layout. Edit carefully: the HTML is shown as it will appear in the page code.',
            'fields' => array_merge([
                ['key' => 'html', 'label' => 'HTML', 'type' => 'code', 'rows' => 18, 'help' => 'Scripts and unsafe code are removed automatically when you save.'],
            ], $section),
        ],
    ];
}

function animations(): array
{
    return ['fade-up' => 'Fade up', 'fade-left' => 'Fade from the right', 'fade-right' => 'Fade from the left', 'zoom-in' => 'Zoom in', '' => 'None'];
}

/** Page-level fields. Product pages get the card fields too. */
function page_fields(string $type): array
{
    $f = [
        ['key' => 'title', 'label' => 'Page name', 'type' => 'text', 'help' => 'Used in the dashboard and as the last step of the breadcrumb.'],
        ['key' => 'path', 'label' => 'Web address', 'type' => 'text', 'help' => 'For example /product/new-fabric.html. If you change it, the old address is redirected automatically.'],
        ['key' => 'seo', 'label' => 'Google & sharing', 'type' => 'group', 'fields' => [
            ['key' => 'title', 'label' => 'Title in Google', 'type' => 'text', 'max' => 60, 'help' => 'Aim for 30–60 characters. Put the most important words first.'],
            ['key' => 'description', 'label' => 'Description in Google', 'type' => 'textarea', 'rows' => 3, 'max' => 160, 'help' => 'Aim for 120–160 characters. Say what the page offers and why to click.'],
            ['key' => 'focusKeyword', 'label' => 'Main search phrase', 'type' => 'text', 'help' => 'The words buyers type into Google to find this page, e.g. “buckram fabric manufacturer”.'],
            ['key' => 'noindex', 'label' => 'Hide this page from Google', 'type' => 'checkbox', 'help' => 'Technical name: noindex.'],
            ['key' => 'ogTitle', 'label' => 'Title when shared (WhatsApp, Facebook, LinkedIn)', 'type' => 'text', 'advanced' => true, 'help' => 'Leave empty to use the Google title.'],
            ['key' => 'ogDescription', 'label' => 'Description when shared', 'type' => 'textarea', 'rows' => 2, 'advanced' => true, 'help' => 'Leave empty to use the Google description.'],
            ['key' => 'ogImage', 'label' => 'Picture when shared', 'type' => 'image', 'advanced' => true, 'help' => 'Leave empty to use the default from Settings.'],
            ['key' => 'canonical', 'label' => 'Official address (canonical)', 'type' => 'url', 'advanced' => true, 'help' => 'Leave empty. Only for pages that copy another page.'],
        ]],
    ];
    if ($type === 'product') {
        $f[] = ['key' => 'card', 'label' => 'Product card (used on the Home and Products pages and in “Related products”)', 'type' => 'group', 'fields' => [
            ['key' => 'title', 'label' => 'Card title', 'type' => 'text'],
            ['key' => 'tag', 'label' => 'Small label', 'type' => 'text', 'placeholder' => 'e.g. Structured'],
            ['key' => 'summary', 'label' => 'One-line summary', 'type' => 'text'],
            ['key' => 'image', 'label' => 'Card picture', 'type' => 'image'],
            ['key' => 'imageAlt', 'label' => 'Picture description', 'type' => 'text'],
        ]];
    }
    $f[] = ['key' => 'breadcrumb', 'label' => 'Breadcrumb steps before this page', 'type' => 'repeater', 'item' => 'step', 'itemLabel' => 'label', 'advanced' => true, 'fields' => [
        ['key' => 'label', 'label' => 'Text', 'type' => 'text'],
        ['key' => 'href', 'label' => 'Links to', 'type' => 'url'],
    ]];
    $f[] = ['key' => 'jsonld', 'label' => 'Extra structured data (JSON-LD)', 'type' => 'json', 'advanced' => true, 'help' => 'For experts. Must be valid JSON (a list).'];
    $f[] = ['key' => 'bodyClass', 'label' => 'Page layout code', 'type' => 'text', 'advanced' => true, 'default' => 'header-fixed-offset'];
    return $f;
}

/** Site-wide settings (site.json). */
function settings_fields(): array
{
    $link = [['key' => 'label', 'label' => 'Text', 'type' => 'text'], ['key' => 'href', 'label' => 'Links to', 'type' => 'url']];
    return [
        ['key' => '', 'type' => 'heading', 'label' => 'Business details'],
        ['key' => 'name', 'label' => 'Legal business name', 'type' => 'text'],
        ['key' => 'brand', 'label' => 'Brand name', 'type' => 'text', 'help' => 'Added to page titles in Google when a page has no title of its own.'],
        ['key' => 'phones', 'label' => 'Phone numbers', 'type' => 'repeater', 'item' => 'phone', 'itemLabel' => 'display', 'fields' => [
            ['key' => 'display', 'label' => 'As shown', 'type' => 'text', 'placeholder' => '+91 99105 55033'],
            ['key' => 'tel', 'label' => 'For dialling (no spaces)', 'type' => 'text', 'placeholder' => '+919910555033'],
        ], 'help' => 'The first number is shown in the top bar.'],
        ['key' => 'email', 'label' => 'Email', 'type' => 'text'],
        ['key' => 'addresses', 'label' => 'Addresses', 'type' => 'repeater', 'item' => 'address', 'itemLabel' => 'label', 'fields' => [
            ['key' => 'label', 'label' => 'Label', 'type' => 'text', 'placeholder' => 'H.O'],
            ['key' => 'text', 'label' => 'Address', 'type' => 'text'],
            ['key' => 'mapUrl', 'label' => 'Google Maps link (if different)', 'type' => 'url'],
        ]],
        ['key' => 'mapUrl', 'label' => 'Google Maps link', 'type' => 'url'],
        ['key' => 'topbarLocation', 'label' => 'Location in the top bar', 'type' => 'text'],
        ['key' => 'hours', 'label' => 'Opening hours', 'type' => 'text'],
        ['key' => 'whatsapp', 'label' => 'WhatsApp', 'type' => 'group', 'fields' => [
            ['key' => 'number', 'label' => 'WhatsApp number (country code, digits only)', 'type' => 'text', 'placeholder' => '919910555033'],
            ['key' => 'message', 'label' => 'Pre-filled message for the green button', 'type' => 'text'],
        ]],
        ['key' => 'social', 'label' => 'Social media profiles', 'type' => 'repeater', 'item' => 'profile', 'itemLabel' => 'label', 'fields' => [
            ['key' => 'label', 'label' => 'Name', 'type' => 'text', 'placeholder' => 'Instagram'],
            ['key' => 'url', 'label' => 'Profile link', 'type' => 'url'],
            ['key' => 'icon', 'label' => 'Icon', 'type' => 'icon', 'placeholder' => 'fab fa-instagram'],
        ]],

        ['key' => '', 'type' => 'heading', 'label' => 'Logo & pictures'],
        ['key' => 'logo', 'label' => 'Logo', 'type' => 'group', 'fields' => [
            ['key' => 'src', 'label' => 'Logo picture', 'type' => 'image'],
            ['key' => 'alt', 'label' => 'Logo description', 'type' => 'text'],
        ]],
        ['key' => 'favicon', 'label' => 'Browser tab icon', 'type' => 'image'],
        ['key' => 'defaultOgImage', 'label' => 'Default picture when a page is shared', 'type' => 'image'],

        ['key' => '', 'type' => 'heading', 'label' => 'Menu'],
        ['key' => 'nav', 'label' => 'Main menu', 'type' => 'repeater', 'item' => 'menu item', 'itemLabel' => 'label', 'fields' => [
            ['key' => 'label', 'label' => 'Text', 'type' => 'text'],
            ['key' => 'href', 'label' => 'Links to', 'type' => 'url'],
            ['key' => 'children', 'label' => 'Drop-down items', 'type' => 'repeater', 'item' => 'drop-down item', 'itemLabel' => 'label', 'fields' => [
                ['key' => 'label', 'label' => 'Text', 'type' => 'text'],
                ['key' => 'href', 'label' => 'Links to', 'type' => 'url'],
                ['key' => 'icon', 'label' => 'Icon', 'type' => 'icon', 'advanced' => true],
                ['key' => 'divider', 'label' => 'Show a dividing line instead', 'type' => 'checkbox', 'advanced' => true],
            ]],
            ['key' => 'page', 'label' => 'Highlight on page (file name)', 'type' => 'text', 'advanced' => true],
            ['key' => 'dropdownId', 'label' => 'Technical name of the drop-down', 'type' => 'text', 'advanced' => true],
        ]],
        ['key' => 'navCta', 'label' => 'Menu button', 'type' => 'group', 'fields' => [
            ['key' => 'label', 'label' => 'Button text', 'type' => 'text'],
            ['key' => 'href', 'label' => 'Links to', 'type' => 'url'],
            ['key' => 'class', 'label' => 'Style (layout code)', 'type' => 'text', 'advanced' => true],
        ]],

        ['key' => '', 'type' => 'heading', 'label' => 'Footer'],
        ['key' => 'newsletter', 'label' => 'Newsletter bar above the footer', 'type' => 'group', 'fields' => [
            ['key' => 'enabled', 'label' => 'Show the newsletter bar', 'type' => 'checkbox'],
            ['key' => 'heading', 'label' => 'Heading', 'type' => 'text'],
            ['key' => 'text', 'label' => 'Text', 'type' => 'text'],
            ['key' => 'placeholder', 'label' => 'Text inside the email box', 'type' => 'text'],
            ['key' => 'button', 'label' => 'Button text', 'type' => 'text'],
        ]],
        ['key' => 'footer', 'label' => 'Footer', 'type' => 'group', 'fields' => [
            ['key' => 'about', 'label' => 'About text', 'type' => 'textarea', 'rows' => 3],
            ['key' => 'logoAlt', 'label' => 'Footer logo description', 'type' => 'text'],
            ['key' => 'columns', 'label' => 'Link columns', 'type' => 'repeater', 'item' => 'column', 'itemLabel' => 'title', 'fields' => [
                ['key' => 'title', 'label' => 'Column heading', 'type' => 'text'],
                ['key' => 'links', 'label' => 'Links', 'type' => 'repeater', 'item' => 'link', 'itemLabel' => 'label', 'fields' => $link],
                ['key' => 'class', 'label' => 'Column width (layout code)', 'type' => 'text', 'advanced' => true],
            ]],
            ['key' => 'contactTitle', 'label' => 'Contact column heading', 'type' => 'text'],
            ['key' => 'contactPerson', 'label' => 'Contact person or team', 'type' => 'text'],
            ['key' => 'copyright', 'label' => 'Copyright line (after © and the year)', 'type' => 'text'],
            ['key' => 'tagline', 'label' => 'Line at the bottom right', 'type' => 'text'],
        ]],

        ['key' => '', 'type' => 'heading', 'label' => 'Visitor statistics'],
        ['key' => 'tracking', 'label' => 'Tracking codes', 'type' => 'group', 'fields' => [
            ['key' => 'ga4', 'label' => 'Google Analytics 4 ID', 'type' => 'text', 'placeholder' => 'G-XXXXXXXXXX'],
            ['key' => 'gtm', 'label' => 'Google Tag Manager ID', 'type' => 'text', 'placeholder' => 'GTM-XXXXXXX'],
            ['key' => 'metaPixel', 'label' => 'Meta (Facebook) Pixel ID', 'type' => 'text', 'placeholder' => '123456789012345'],
        ], 'help' => 'Leave empty if you don’t use them. They are added to every public page (never to the dashboard).'],
    ];
}

/** A new page of a given type starts with these sections. */
function page_starter(string $type, string $title): array
{
    $hero = ['type' => 'page-hero', 'heading' => $title, 'eyebrow' => '', 'icon' => '', 'intro' => '', 'image' => '', 'breadcrumb' => true];
    $cta = ['type' => 'cta', 'heading' => 'Ready To Discuss Your Next Bulk Order?', 'text' => 'Tell us your product, quantity and timeline — our team will respond with pricing and lead time within one business day.', 'buttons' => []];
    if ($type === 'product') {
        return [
            $hero,
            ['type' => 'media-text', 'eyebrow' => 'Product Overview', 'heading' => '', 'html' => '<p class="mb-4"></p>', 'checklist' => [], 'image' => '', 'imageAlt' => '', 'button' => ['label' => 'Request a Quote', 'href' => '/contact.html', 'class' => 'btn btn-navy', 'iconAfter' => 'fas fa-arrow-right ms-2']],
            ['type' => 'card-grid', 'bg' => 'bg-soft', 'eyebrow' => "Where It's Used", 'heading' => 'Applications', 'card' => 'value', 'centered' => true, 'col' => 'col-lg-3 col-sm-6', 'items' => []],
            ['type' => 'spec-table', 'eyebrow' => 'Product Specifications', 'heading' => 'Typical Specifications', 'columns' => ['Parameter', 'Typical Specification'], 'rows' => []],
            ['type' => 'faq', 'eyebrow' => 'Common Questions', 'heading' => $title . ' FAQs', 'accordionId' => slugify($title) . '-faq', 'itemPrefix' => slugify($title) . '-faq-', 'items' => [],
                'button' => ['label' => 'Ask Us Directly', 'href' => '/contact.html', 'class' => 'btn btn-navy mt-2']],
            ['type' => 'product-cards', 'bg' => 'bg-soft', 'eyebrow' => 'You May Also Need', 'heading' => 'Related Products', 'center' => true, 'items' => [],
                'link' => ['label' => 'View Full Product Range', 'href' => '/products.html', 'class' => 'btn btn-outline-navy', 'iconAfter' => 'fas fa-arrow-right ms-2']],
            $cta,
        ];
    }
    return [$hero, ['type' => 'media-text', 'heading' => '', 'html' => '<p class="mb-4"></p>', 'image' => '', 'imageAlt' => ''], $cta];
}

function page_type_labels(): array
{
    return [
        'home'    => ['Home page', 'fa-home'],
        'product' => ['Product page', 'fa-box'],
        'listing' => ['Product list', 'fa-th-large'],
        'about'   => ['About page', 'fa-info-circle'],
        'contact' => ['Contact page', 'fa-envelope'],
        'page'    => ['Information page', 'fa-file-alt'],
    ];
}
