<?php
namespace OfferWeave;

/** Free package implementation. All functionality in this file is available without an upgrade. */
final class FrontendView
{
    private array $config;
    private array $public;
    private array $spec;
    private array $state;
    private array $offers = [];
    private array $scope = [];
    private array $shared = [];
    private bool $independent = false;
    private string $prefix;
    private string $category = '';
    private bool $requestSection = false;
    private static array $instances = [];

    public function __construct(
        array $config,
        array $spec,
        array $state = [],
        array $preview = [],
        ?array $public = null,
    ) {
        $this->config = $config;
        $this->public = $public ?? Config::publicConfig($config, time());
        $this->spec = array_replace(
            [
                'view' => 'catalog',
                'offer' => '',
                'category' => '',
                'title' => '',
                'actions' => 'true',
                'connect' => '',
                'controls' => 'auto',
                'promotion' => '',
            ],
            $spec,
        );
        $this->spec['locale'] = $spec['locale'] ?? I18n::current();
        unset($this->spec['id']);
        ksort($this->spec);
        $this->state = $state;
        $this->prefix = 'ow-' . FrontendState::scope($this->spec);
        $this->configure();
    }
    public function publicConfig(): array
    {
        return $this->public;
    }
    public function spec(): array
    {
        return $this->spec;
    }
    private function offer(string $id): ?array
    {
        foreach ($this->public['offers'] as $offer) {
            if ($offer['id'] === $id) {
                return $offer;
            }
        }
        return null;
    }
    private function configure(): void
    {
        $category = $this->spec['category'];
        if ($this->spec['view'] === 'builder') {
            // Public, read-only navigation: no stored state changes; values are allowlisted below.
            $section = sanitize_key(
                wp_unslash($_GET['offerweave_section'][FrontendState::scope($this->spec)] ?? ''),
            );
            $choices = array_column($this->public['offers'], 'category');
            if (is_string($section) && in_array($section, $choices, true)) {
                $category = $section;
            }
            $this->requestSection = $section === 'selection';
        }
        if ($this->spec['view'] === 'builder' && !$category) {
            foreach ($this->public['offers'] as $offer) {
                if ($offer['catalog_visible']) {
                    $category = $offer['category'];
                    break;
                }
            }
        }
        $this->category = $category;
        $this->offers = array_values(
            array_filter(
                $this->public['offers'],
                fn($o) => $o['catalog_visible'] &&
                    (!$category || $o['category'] === $category) &&
                    (!$this->spec['offer'] || $o['id'] === $this->spec['offer']),
            ),
        );
        $this->scope = $this->offers;
        $this->independent = $this->public['settings']['input_scope'] === 'offer';
        $this->shared = array_replace(['count' => 1], $this->state['shared'] ?? []);
    }
    public function input(array $offer): array
    {
        $raw = $this->state['inputs'][$offer['id']] ?? [];
        return [
            'line_id' => substr(hash('sha256', $this->prefix . $offer['id']), 0, 32),
            'offer_id' => $offer['id'],
            'quantity' => $this->independent ? $raw['count'] ?? 1 : $this->shared['count'],
        ];
    }
    public function render(): string
    {
        $scope = FrontendState::scope($this->spec);
        $instance = self::$instances[$scope] = (self::$instances[$scope] ?? 0) + 1;
        $suffix = $instance > 1 ? '-instance-' . $instance : '';
        $this->prefix = 'ow-' . $scope . $suffix;
        $view = $this->spec['view'];
        $class = 'cqb-app' . ($view === 'add' ? ' cqb-purchase-only' : '');
        $out = '<div id="offerweave-' . $scope . $suffix . '" class="' . $class . '" data-ow-ssr="true"';
        foreach (['view', 'offer', 'category', 'title', 'actions'] as $key) {
            $out .= ' data-' . $key . '="' . esc_attr($this->spec[$key]) . '"';
        }
        $out .= FrontendDesign::attributes(FrontendDesign::resolve($this->public)) . '>';
        $out .=
            '<template data-ow-state>' .
            esc_html(
                wp_json_encode([
                    'cart' => $this->state['cart'] ?? [],
                    'can_import' => empty(FrontendState::data()['initialized']),
                    'revision' => Config::revision($this->config),
                    'server_time' => time(),
                    'failed' => !empty($this->state['failure']['error']),
                ]),
            ) .
            '</template>';
        if ($view === 'selection') {
            return $out . '<div class="cqb-selection-button">' . $this->selectionLink() . '</div></div>';
        }
        if ($view === 'builder') {
            $out .=
                '<header class="cqb-header"><div><div class="cqb-brand">' .
                esc_html($this->public['settings']['brand']) .
                '</div><p>' .
                esc_html__('Your quote tailored to your needs', 'offerweave') .
                '</p></div>' .
                $this->selectionLink() .
                '</header>' .
                $this->navigation();
        }
        $out .=
            '<div class="cqb-global-message" role="status">' .
            esc_html($this->state['failure']['error'] ?? ($this->state['message'] ?? '')) .
            '</div><div class="cqb-content">';
        if ($view === 'request' || $this->requestSection) {
            return $out .
                (new FrontendRequest($this->config, $this->spec, $this->state))->render() .
                '</div></div>';
        }
        if (!$this->offers) {
            return $out .
                '<p class="cqb-empty" role="status">' .
                esc_html(
                    $this->spec['offer']
                        ? __('This offer is currently unavailable.', 'offerweave')
                        : __('No offers are currently available in this category.', 'offerweave'),
                ) .
                '</p></div></div>';
        }
        if ($view === 'builder' || $this->spec['title']) {
            $out .=
                '<div class="cqb-lead"><span class="cqb-eyebrow">' .
                esc_html__('Your requested scope', 'offerweave') .
                '</span><h2>' .
                esc_html($this->spec['title'] ?: ($this->offers[0]['category_label'] ?: $this->category)) .
                '</h2><p>' .
                esc_html($this->public['settings']['intro']) .
                '</p></div>';
        }
        $out .= $this->sharedControls() . '<p class="cqb-calculation-message" role="status"></p>';
        $out .=
            '<div class="cqb-cards" style="--cqb-columns:' .
            min(3, count($this->offers)) .
            ';--cqb-columns-small:' .
            min(2, count($this->offers)) .
            ';--cqb-columns-mobile:1;">';
        foreach ($this->offers as $offer) {
            $out .= $this->card($offer);
        }
        $out .= '</div>';
        if ($this->public['legal']['show_catalog_notice'] ?? false) {
            $out .= $this->legal();
        }
        return $out . '</div></div>';
    }
    private function sectionUrl(string $section): string
    {
        return add_query_arg(
            ['offerweave_section' => [FrontendState::scope($this->spec) => $section]],
            FrontendState::pageUrl(),
        ) .
            '#offerweave-' .
            FrontendState::scope($this->spec);
    }
    private function navigation(): string
    {
        $categories = [];
        foreach ($this->public['offers'] as $offer) {
            if ($offer['catalog_visible']) {
                $categories[$offer['category']] =
                    $offer['category_label'] ?: ucfirst(str_replace(['-', '_'], ' ', $offer['category']));
            }
        }
        $categories['selection'] = __('Selection & request', 'offerweave');
        $out = '<nav class="cqb-nav" aria-label="' . esc_attr__('Offer sections', 'offerweave') . '">';
        foreach ($categories as $id => $label) {
            $out .=
                '<a href="' .
                esc_url($this->sectionUrl($id)) .
                '" data-category-tab="' .
                esc_attr($id) .
                '" aria-current="' .
                (($this->requestSection ? 'selection' : $this->category) === $id ? 'page' : 'false') .
                '">' .
                esc_html($label) .
                '</a>';
        }
        return $out . '</nav>';
    }
    private function selectionLink(): string
    {
        $url =
            $this->spec['view'] === 'builder'
                ? $this->sectionUrl('selection')
                : ($this->public['settings']['selection_url'] ?:
                '#offerweave-request');
        return '<a class="cqb-button cqb-outline" data-selection href="' .
            esc_url($url) .
            '">' .
            esc_html__('My selection', 'offerweave') .
            ' <span data-counter>(' .
            count($this->state['cart'] ?? []) .
            ')</span></a>';
    }
    public function sharedControls(): string
    {
        if ($this->independent) {
            return '';
        }
        $s = $this->public['settings'];
        $labels = array_unique(
            array_map(static fn($o) => $o['input_unit_plural'] ?: $o['input_unit_singular'], $this->scope),
        );
        $label = count($labels) === 1 ? (reset($labels) ?: '') : '';
        return FrontendState::formOpen($this->spec, $this->prefix . '-shared', 'cqb-controls') .
            $this->number(
                $s['quantity_label'] ?: ($label ?: __('Quantity', 'offerweave')),
                'shared[count]',
                $this->shared['count'],
                'data-people',
            ) .
            '<p class="cqb-note">' .
            esc_html($s['scope_help'] ?: __('Choose the scope you need.', 'offerweave')) .
            '</p>' .
            $this->updateButton() .
            '</form>';
    }
    public function cardControls(array $offer, array $input, bool $force = false): string
    {
        if (!$force && !$this->independent) {
            return '';
        }
        $s = $this->public['settings'];
        return '<div class="cqb-offer-quantity cqb-card-controls">' .
            $this->number(
                $offer['input_unit_plural'] ?:
                ($s['individual_quantity_label'] ?:
                __('Quantity for this offer', 'offerweave')),
                'inputs[' . $offer['id'] . '][count]',
                $input['quantity'] ?? 1,
                'data-quantity',
            ) .
            '</div>';
    }
    private function number(
        string $label,
        string $name,
        $value,
        string $data,
        int $min = 1,
        int $max = 100000,
        string $step = '1',
    ): string {
        return '<label><span>' .
            esc_html($label) .
            '</span><input type="number" name="offerweave_' .
            esc_attr($name) .
            '" min="' .
            $min .
            '" max="' .
            $max .
            '" step="' .
            esc_attr($step) .
            '" value="' .
            esc_attr((string) $value) .
            '" ' .
            $data .
            ' inputmode="' .
            ($step === '1' ? 'numeric' : 'decimal') .
            '"></label>';
    }
    public function card(array $offer): string
    {
        $purchase = $this->spec['view'] === 'add';
        $input = [];
        $line = null;
        $error = '';
        try {
            $input = $this->input($offer);
            $line = Pricing::quote($this->config, [$input])['items'][0];
        } catch (\DomainException $exception) {
            $error = $exception->getMessage();
        }
        $selected = in_array($offer['id'], array_column($this->state['cart'] ?? [], 'offer_id'), true);
        $image = $offer['image'] ?? [];
        $out =
            '<article class="cqb-card" data-card="' .
            esc_attr($offer['id']) .
            '" data-selected="' .
            ($selected ? 'true' : 'false') .
            '">';
        $out .= FrontendState::formOpen($this->spec, $this->prefix . '-' . $offer['id'], 'cqb-card-layout');
        $out .=
            '<input type="hidden" name="offerweave_offer" value="' .
            esc_attr($offer['id']) .
            '"><template data-ow-quote>' .
            esc_html(wp_json_encode($line)) .
            '</template>';
        if (!$purchase && !empty($image['url'])) {
            $out .=
                '<div class="cqb-card-media"><img class="cqb-offer-image" src="' .
                esc_url($image['url']) .
                '" alt="' .
                esc_attr($image['decorative'] ? '' : $image['alt']) .
                '" loading="lazy" decoding="async"></div>';
        }
        $out .= '<div class="cqb-card-main">';
        if (!$purchase) {
            if ($offer['eyebrow']) {
                $out .= '<p class="cqb-eyebrow">' . esc_html($offer['eyebrow']) . '</p>';
            }
            $out .= '<h3>' . esc_html($offer['name']) . '</h3>';
            if ($offer['badge']) {
                $out .= '<p class="cqb-note">' . esc_html($offer['badge']) . '</p>';
            }
            if ($offer['description']) {
                $out .= '<p class="cqb-description">' . esc_html($offer['description']) . '</p>';
            }
        }
        $out .=
            '<div class="cqb-price" data-price>' .
            FrontendPrice::render($this->public, $line, $offer, $this->spec['view']) .
            '</div>' .
            $this->cardControls($offer, $input);
        if (!$purchase && $offer['facts']) {
            $out .=
                '<ul class="cqb-card-facts" aria-label="' . esc_attr__('At a glance', 'offerweave') . '">';
            foreach ($offer['facts'] as $i => $fact) {
                $icon = $offer['fact_icons'][$i]['url'] ?? '';
                $out .=
                    '<li>' .
                    ($icon
                        ? '<img class="cqb-fact-icon" src="' .
                            esc_url($icon) .
                            '" alt="" width="20" height="20" loading="lazy" decoding="async">'
                        : '') .
                    '<span>' .
                    esc_html($fact) .
                    '</span></li>';
            }
            $out .= '</ul>';
        }
        $out .= $this->updateButton();
        if (!$purchase && $offer['features']) {
            if ($offer['content_heading']) {
                $out .=
                    '<strong class="cqb-content-heading">' .
                    esc_html($offer['content_heading']) .
                    '</strong>';
            }
            $out .= '<ul class="cqb-features">';
            foreach ($offer['features'] as $feature) {
                $out .= '<li><span aria-hidden="true">✓</span>' . esc_html($feature) . '</li>';
            }
            $out .= '</ul>';
        }
        $out .= '<div data-breakdown>' . ($line ? FrontendPrice::breakdown($line) : '') . '</div></div>';
        $out .=
            '<footer' .
            ($this->spec['actions'] === 'false' ? ' hidden' : '') .
            '><p class="cqb-card-error" role="status">' .
            esc_html($error) .
            '</p><div class="cqb-card-actions"><button type="submit" name="offerweave_action" value="add" class="cqb-button" data-add' .
            (!$line ? ' disabled' : '') .
            ' aria-label="' .
            /* translators: Offer name. */
            esc_attr(sprintf(__('Add %s to selection', 'offerweave'), $offer['name'])) .
            '">' .
            esc_html__('Add to selection', 'offerweave') .
            '</button>';
        if (!$purchase && $offer['detail_url']) {
            $out .=
                '<a class="cqb-detail-link" href="' .
                esc_url($offer['detail_url']) .
                '">' .
                esc_html__('View details', 'offerweave') .
                '</a>';
        }
        return $out . '</div></footer></form></article>';
    }
    private function updateButton(string $form = ''): string
    {
        return '<button type="submit" class="cqb-link ow-update" name="offerweave_action" value="update" formnovalidate' .
            ($form ? ' form="' . esc_attr($form) . '"' : '') .
            '>' .
            esc_html__('Update price', 'offerweave') .
            '</button>';
    }
    public function legal(): string
    {
        $legal = $this->public['legal'];
        return '<aside class="cqb-enquiry-information cqb-note"><p>' .
            esc_html($legal['notice']) .
            '</p>' .
            ($legal['audience_notice']
                ? '<p><strong>' . esc_html($legal['audience_notice']) . '</strong></p>'
                : '') .
            ($legal['imprint_url']
                ? '<p><a href="' .
                    esc_url($legal['imprint_url']) .
                    '">' .
                    esc_html__('Provider information', 'offerweave') .
                    '</a></p>'
                : '') .
            '</aside>';
    }
}
