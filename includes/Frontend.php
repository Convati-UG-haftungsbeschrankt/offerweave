<?php
namespace OfferWeave;

final class Frontend
{
    private static bool $booted = false;
    public static function register(): void
    {
        add_shortcode('offerweave', fn($a) => self::render('builder', $a));
        add_shortcode('offerweave_promotions', static fn() => '');
        add_shortcode('offerweave_controls', static fn() => '');
        add_shortcode('offerweave_catalog', fn($a) => self::render('catalog', $a));
        add_shortcode('offerweave_offer', fn($a) => self::render('offer', $a));
        add_shortcode('offerweave_add', fn($a) => self::render('add', $a));
        add_shortcode('offerweave_selection', fn($a) => self::render('selection', $a));
        add_shortcode('offerweave_request', fn($a) => self::render('request', $a));
        foreach (
            ['phishing' => 'phishing', 'workshops' => 'workshops', 'modules' => 'module']
            as $name => $category
        ) {
            add_shortcode(
                'offerweave_' . $name,
                fn($a) => self::render(
                    'catalog',
                    array_merge((array) $a, [
                        'category' => $category === 'module' ? self::previousCategory() : $category,
                    ]),
                ),
            );
        }
        LegacyShortcodes::register([self::class, 'render'], static fn() => self::previousCategory());
    }
    private static function previousCategory(): string
    {
        try {
            $offers = Config::get()['offers'];
        } catch (\DomainException $error) {
            // Still mount the legacy block: its REST session displays the catalog error safely as text.
            return 'module';
        }
        foreach ($offers as $offer) {
            if ($offer['id'] === 'module-individuell') {
                return $offer['category'];
            }
        }
        return 'module';
    }
    public static function specForTag(string $tag, array $atts): ?array
    {
        $views = [
            'offerweave' => 'builder',
            'offerweave_catalog' => 'catalog',
            'offerweave_offer' => 'catalog',
            'offerweave_add' => 'add',
            'offerweave_request' => 'request',
            'offerweave_selection' => 'selection',
            'cqb_builder' => 'builder',
            'cqb_catalog' => 'catalog',
            'cqb_request' => 'request',
            'rq_request' => 'request',
        ];
        foreach (
            ['phishing' => 'phishing', 'workshops' => 'workshops', 'modules' => self::previousCategory()]
            as $alias => $category
        ) {
            if (in_array($tag, ['offerweave_' . $alias, 'cqb_' . $alias], true)) {
                $views[$tag] = 'catalog';
                $atts['category'] = $category;
            }
        }
        if (!isset($views[$tag])) {
            return null;
        }
        return self::normalizeSpec($views[$tag], $atts);
    }
    public static function normalizeSpec(string $view, array $atts): array
    {
        $a = shortcode_atts(
            [
                'category' => '',
                'offer' => '',
                'id' => '',
                'title' => '',
                'actions' => 'true',
                'promotion' => '',
                'connect' => '',
                'controls' => 'auto',
            ],
            $atts,
        );
        foreach ($a as $value) {
            if (!is_scalar($value)) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text domain error; the PHP renderer escapes HTML and JavaScript uses textContent at the final output.
                throw new \DomainException(__('Invalid selection.', 'offerweave'));
            }
        }
        $a['offer'] = (string) ($a['id'] ?: $a['offer']);
        unset($a['id']);
        $a['category'] = sanitize_key($a['category']);
        $a['title'] = sanitize_text_field($a['title']);
        $a['view'] = $view === 'offer' ? 'catalog' : $view;
        $a['locale'] = I18n::website();
        $a['actions'] = in_array(strtolower((string) $a['actions']), ['false', '0', 'off'], true)
            ? 'false'
            : 'true';
        $a['controls'] = 'auto';
        $a['connect'] = '';
        $a['promotion'] = '';
        ksort($a);
        return $a;
    }
    public static function render(string $view, $atts): string
    {
        return I18n::run(I18n::websiteLocale(), fn() => self::renderView($view, $atts));
    }
    private static function renderView(string $view, $atts): string
    {
        $a = shortcode_atts(
            [
                'category' => '',
                'offer' => '',
                'id' => '',
                'title' => '',
                'actions' => 'true',
                'promotion' => '',
                'connect' => '',
                'controls' => 'auto',
            ],
            (array) $atts,
        );
        $a['controls'] = 'auto';
        $a['connect'] = '';
        $a['promotion'] = '';
        if (
            ($a['id'] !== '' && $a['offer'] !== '' && $a['id'] !== $a['offer']) ||
            (in_array($view, ['offer', 'add'], true) && $a['id'] === '' && $a['offer'] === '')
        ) {
            return '<p class="cqb-shortcode-error" role="alert">' .
                esc_html__('Please specify a unique offer ID in the shortcode.', 'offerweave') .
                '</p>';
        }
        $id = $a['id'] !== '' ? $a['id'] : $a['offer'];
        if ($id !== '' && (!is_string($id) || !preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $id))) {
            return '<p class="cqb-shortcode-error" role="alert">' .
                esc_html__('The offer ID in the shortcode is invalid.', 'offerweave') .
                '</p>';
        }
        if ($view === 'offer') {
            $view = 'catalog';
        }
        $a['offer'] = $id;

        wp_enqueue_style(
            'offerweave-frontend',
            OFFERWEAVE_URL . 'assets/frontend.css',
            [],
            OFFERWEAVE_VERSION,
        );
        wp_enqueue_script(
            'offerweave-money',
            OFFERWEAVE_URL . 'assets/money.js',
            [],
            OFFERWEAVE_VERSION,
            true,
        );
        $dependencies = ['wp-i18n', 'offerweave-money'];
        wp_enqueue_script(
            'offerweave-frontend',
            OFFERWEAVE_URL . 'assets/frontend.js',
            $dependencies,
            OFFERWEAVE_VERSION,
            true,
        );
        wp_set_script_translations('offerweave-frontend', 'offerweave');
        if (!self::$booted) {
            wp_add_inline_script(
                'offerweave-frontend',
                'window.offerweave_boot=' .
                    wp_json_encode(
                        [
                            'api' => rest_url(Api::NS . '/'),
                            'version' => OFFERWEAVE_VERSION,
                            'currencies' => Currency::catalog(),
                            'locale' => I18n::website(),
                        ],
                        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
                    ) .
                    ';',
                'before',
            );
            self::$booted = true;
        }
        try {
            $config = Translations::apply(Config::runtime());
            $a = self::normalizeSpec($view, $a);
            return (new FrontendView($config, $a, FrontendState::state($a)))->render();
        } catch (\DomainException $error) {
            return '<p class="cqb-shortcode-error" role="alert">' . esc_html($error->getMessage()) . '</p>';
        }
    }
}
