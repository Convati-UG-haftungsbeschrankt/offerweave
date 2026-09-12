<?php
namespace OfferWeave;

/** Selection and contact form use the same PHP view in native and enhanced requests. */
final class FrontendRequest
{
    private array $config;
    private array $public;
    private array $spec;
    private array $state;
    private FrontendView $view;
    public function __construct(array $config, array $spec, array $state)
    {
        $this->config = $config;
        $this->public = Config::publicConfig($config);
        $this->spec = $spec;
        $this->state = $state;
        $this->view = new FrontendView($config, $spec, $state);
    }
    public function render(): string
    {
        $out =
            '<div class="cqb-lead" id="offerweave-request"><span class="cqb-eyebrow">' .
            esc_html__('Everything at a glance', 'offerweave') .
            '</span><h2>' .
            esc_html__('Your selection', 'offerweave') .
            '</h2><p>' .
            esc_html__(
                'Review the services and scope. Your request does not create a binding booking.',
                'offerweave',
            ) .
            '</p></div><div class="cqb-cart">';
        $quote = null;
        $error = '';
        $cart = $this->state['cart'] ?? [];
        if (!$cart) {
            $out .=
                '<div class="cqb-empty">' . esc_html__('Your selection is empty.', 'offerweave') . '</div>';
        } else {
            try {
                $quote = Api::quoteResult($this->config, $cart);
            } catch (\DomainException $e) {
                $error = $e->getMessage();
            }
            $out .=
                '<div class="cqb-cart-header"><span>' .
                esc_html__('Selection', 'offerweave') .
                '</span><span>' .
                esc_html(_x('Scope', 'Original label: Umfang', 'offerweave')) .
                '</span><span>' .
                esc_html(
                    $this->public['tax']['enabled']
                        ? __('Price and VAT', 'offerweave')
                        : __('Net price', 'offerweave'),
                ) .
                '</span><span>' .
                esc_html__('Actions', 'offerweave') .
                '</span></div>';
            foreach ($cart as $raw) {
                $offer = null;
                $line = null;
                foreach ($this->public['offers'] as $o) {
                    if ($o['id'] === $raw['offer_id']) {
                        $offer = $o;
                    }
                }
                foreach ($quote['quote']['items'] ?? [] as $item) {
                    if ($item['input']['line_id'] === $raw['line_id']) {
                        $line = $item;
                    }
                }
                $out .=
                    '<div class="cqb-cart-row" data-offer="' .
                    esc_attr($raw['offer_id']) .
                    '" data-line="' .
                    esc_attr($raw['line_id']) .
                    '"' .
                    FrontendDesign::attributes(FrontendDesign::resolve($this->public, $offer)) .
                    '><div><strong>' .
                    esc_html($offer['name'] ?? $raw['offer_id']) .
                    '</strong></div><div data-details>' .
                    ($line
                        ? implode('<br>', array_map('esc_html', $line['details'])) .
                            FrontendPrice::breakdown($line)
                        : '') .
                    '</div><div class="cqb-line-price" data-line-price>' .
                    ($line
                        ? FrontendPrice::render($this->public, $line, null, '', true) .
                            FrontendPrice::unitHelp($line, $offer)
                        : '—') .
                    '</div><div class="cqb-row-actions">' .
                    FrontendState::formOpen($this->spec, 'offerweave-remove-' . $raw['line_id']) .
                    '<input type="hidden" name="offerweave_line" value="' .
                    esc_attr($raw['line_id']) .
                    '"><button type="submit" class="cqb-link" name="offerweave_action" value="remove" data-remove>' .
                    esc_html__('Remove', 'offerweave') .
                    '</button></form></div>';
                if ($offer) {
                    $out .= $this->editor($offer, $raw);
                }
                $out .= '</div>';
            }
            if ($quote) {
                $out .=
                    '<div class="cqb-cart-summary" aria-live="polite">' .
                    self::summary($quote['quote']['summary']) .
                    '</div>';
            }
            $requirement = $quote['quote']['request_requirement'] ?? [];
            if (($requirement['eligible'] ?? true) === false) {
                $error = $requirement['message'];
            }
            $out .= '<p class="cqb-cart-error" role="alert">' . esc_html($error) . '</p>';
        }
        return $out . '</div><div class="cqb-request-form">' . $this->form($quote, $error !== '') . '</div>';
    }
    private function editor(array $offer, array $raw): string
    {
        $id = 'offerweave-edit-' . $raw['line_id'];
        $out =
            '<details class="cqb-line-editor"><summary class="cqb-link" data-edit>' .
            esc_html__('Edit', 'offerweave') .
            '</summary>' .
            FrontendState::formOpen($this->spec, $id) .
            '<input type="hidden" name="offerweave_line" value="' .
            esc_attr($raw['line_id']) .
            '"><input type="hidden" name="offerweave_offer" value="' .
            esc_attr($offer['id']) .
            '">' .
            $this->view->cardControls($offer, $raw, true) .
            '<p class="cqb-edit-error" role="alert"></p><button type="submit" class="cqb-button" name="offerweave_action" value="save-line" data-save-line>' .
            esc_html__('Apply changes', 'offerweave') .
            '</button></form></details>';
        return str_replace(['data-quantity ', 'data-people '], 'data-count ', $out);
    }
    private function form(?array $quote, bool $invalid): string
    {
        $s = $this->public['settings'];
        $values = $this->state['failure']['fields'] ?? [];
        $out =
            '<h3>' .
            esc_html__('Send request', 'offerweave') .
            '</h3><p class="cqb-note">' .
            esc_html__('Fields marked * are required.', 'offerweave') .
            '</p>' .
            FrontendState::formOpen(
                $this->spec,
                'offerweave-request-' . FrontendState::scope($this->spec),
                'cqb-form',
            ) .
            '<div class="cqb-form-grid">';
        foreach ($this->public['fields'] as $field) {
            $value = $values[$field['id']] ?? '';
            $id = 'offerweave-field-' . FrontendState::scope($this->spec) . '-' . $field['id'];
            $attr =
                ' name="offerweave_field_' .
                esc_attr($field['id']) .
                '" id="' .
                esc_attr($id) .
                '" data-field="' .
                esc_attr($field['id']) .
                '"' .
                ($field['required'] ? ' required' : '');
            if ($field['type'] === 'textarea') {
                $input =
                    '<textarea' .
                    $attr .
                    ' rows="4" maxlength="4000" placeholder="' .
                    esc_attr($field['placeholder']) .
                    '">' .
                    esc_textarea(is_scalar($value) ? (string) $value : '') .
                    '</textarea>';
            } elseif ($field['type'] === 'select') {
                $input =
                    '<select' .
                    $attr .
                    '><option value="">' .
                    esc_html__('Please select', 'offerweave') .
                    '</option>';
                foreach ($field['options'] as $index => $option) {
                    $input .=
                        '<option value="' .
                        esc_attr($option) .
                        '"' .
                        ($value === $option ? ' selected' : '') .
                        '>' .
                        esc_html($field['option_labels'][$index] ?? $option) .
                        '</option>';
                }
                $input .= '</select>';
            } elseif ($field['type'] === 'checkbox') {
                $input =
                    '<input type="checkbox" value="1"' . $attr . ($value === true ? ' checked' : '') . '>';
            } else {
                $input =
                    '<input type="' .
                    esc_attr($field['type']) .
                    '"' .
                    $attr .
                    ' value="' .
                    esc_attr(is_scalar($value) ? (string) $value : '') .
                    '"' .
                    ($field['type'] === 'number' ? ' step="any"' : '') .
                    ' maxlength="300" placeholder="' .
                    esc_attr($field['placeholder']) .
                    '" autocomplete="' .
                    esc_attr(
                        ['name' => 'name', 'company' => 'organization', 'email' => 'email', 'phone' => 'tel'][
                            $field['id']
                        ] ?? 'off',
                    ) .
                    '">';
            }
            $label = esc_html($field['label']) . ($field['required'] ? ' *' : '');
            $out .=
                '<div class="cqb-field' .
                ($field['type'] === 'textarea' ? ' cqb-full' : '') .
                '">' .
                ($field['type'] === 'checkbox'
                    ? '<label class="cqb-checkbox">' . $input . '<span>' . $label . '</span></label>'
                    : '<label for="' . esc_attr($id) . '">' . $label . '</label>' . $input) .
                '</div>';
        }
        $out .=
            '</div><div class="cqb-honeypot" aria-hidden="true"><label>' .
            esc_html__('Website', 'offerweave') .
            '<input name="website" tabindex="-1" autocomplete="off"></label></div><p class="cqb-note">' .
            esc_html($s['privacy_text']) .
            ($s['privacy_url']
                ? ' <a href="' .
                    esc_url($s['privacy_url']) .
                    '" target="_blank" rel="noopener">' .
                    esc_html__('Privacy notice', 'offerweave') .
                    '</a>'
                : '') .
            '</p>' .
            $this->view->legal();
        $data = [
            'nonce' => wp_create_nonce('offerweave_submit'),
            'form_token' => Spam::formToken(),
            'request_key' => bin2hex(random_bytes(16)),
            'signature' => $quote['signature'] ?? '',
            'expires' => $quote['expires'] ?? 0,
        ];
        foreach ($data as $key => $value) {
            $out .=
                '<input type="hidden" name="' .
                esc_attr($key) .
                '" value="' .
                esc_attr((string) $value) .
                '">';
        }
        $out .=
            '<input type="hidden" name="captcha_token" value=""><div class="cqb-captcha" data-provider="' .
            esc_attr($s['captcha_provider']) .
            '" data-sitekey="' .
            esc_attr($s['captcha_site_key']) .
            '"></div>';
        if ($s['captcha_provider'] !== 'none') {
            $out .=
                '<noscript><p class="cqb-note">' .
                esc_html__(
                    'This provider uses a CAPTCHA that requires JavaScript. Enable JavaScript to send this form or contact the provider directly.',
                    'offerweave',
                ) .
                '</p></noscript>';
        }
        return $out .
            '<p class="cqb-form-message" role="alert">' .
            esc_html($this->state['failure']['error'] ?? '') .
            '</p><button class="cqb-button cqb-submit" type="submit" name="offerweave_action" value="submit"' .
            (!$quote || $invalid ? ' disabled' : '') .
            '>' .
            esc_html($s['submit_label']) .
            '</button></form>';
    }
    public static function summary(array $s): string
    {
        $currency = $s['currency'];
        $out =
            '<div class="cqb-totals' .
            (isset($s['tax']) ? ' cqb-tax-totals' : '') .
            '" aria-label="' .
            esc_attr__('Price summary', 'offerweave') .
            '">';
        if (isset($s['tax'])) {
            foreach ($s['tax']['groups'] as $group) {
                $out .=
                    '<section class="cqb-tax-group"><h3>' .
                    esc_html($group['label']) .
                    '</h3>' .
                    FrontendPrice::taxRows($group['rows'], false, $currency) .
                    '</section>';
            }
            if ($s['has_custom'] || $s['has_travel']) {
                $out .=
                    '<p>' .
                    esc_html__(
                        'Only known amounts are included. Outstanding services or travel costs and their VAT will be added.',
                        'offerweave',
                    ) .
                    '</p>';
            }
            return $out .
                '<p class="cqb-note">' .
                esc_html__(
                    'Net amounts, VAT and gross amounts are shown separately. This price summary is non-binding. Term totals assume an unchanged scope. VAT is rounded per item and billing period; totals add these rounded amounts.',
                    'offerweave',
                ) .
                '</p></div>';
        }
        if ($s['priced_count']) {
            $out .=
                '<div><span>' .
                esc_html(
                    $s['has_custom'] || $s['has_travel']
                        ? __('Known one-time amount', 'offerweave')
                        : __('One-time', 'offerweave'),
                ) .
                '</span><strong>' .
                FrontendPrice::money($s['once_cents'], $currency) .
                '</strong></div>';
            if ($s['terms']) {
                $out .=
                    '<div><span>' .
                    esc_html__('Monthly', 'offerweave') .
                    '</span><strong>' .
                    FrontendPrice::money($s['monthly_cents'], $currency) .
                    ' ' .
                    esc_html__('/ month', 'offerweave') .
                    '</strong></div>';
            }
        }
        foreach ($s['terms'] as $term) {
            $out .=
                '<div class="cqb-subtotal"><span>' .
                esc_html__('Monthly services with a', 'offerweave') .
                ' ' .
                $term['months'] .
                ' ' .
                esc_html(_x('month term', 'Original label: Monaten Laufzeit', 'offerweave')) .
                '</span><span>' .
                FrontendPrice::money($term['total_cents'], $currency) .
                ' ' .
                esc_html__('for', 'offerweave') .
                ' ' .
                $term['months'] .
                ' ' .
                esc_html__('months', 'offerweave') .
                '</span></div>';
        }
        if ($s['comparison_cents'] !== null) {
            $out .=
                '<div class="cqb-comparison"><span>' .
                esc_html__('For', 'offerweave') .
                ' ' .
                $s['comparison_months'] .
                ' ' .
                esc_html__('months, including one-time services', 'offerweave') .
                '</span><strong>' .
                FrontendPrice::money($s['comparison_cents'], $currency) .
                '</strong></div>';
        }
        if ($s['has_custom']) {
            $out .=
                '<p>' . esc_html__('Additional items will be quoted individually.', 'offerweave') . '</p>';
        }
        if ($s['has_travel']) {
            $out .=
                '<p>' .
                esc_html(
                    _x(
                        'Outstanding travel costs will be added.',
                        'Original label: Offene Reisekosten kommen hinzu.',
                        'offerweave',
                    ),
                ) .
                '</p>';
        }
        return $out .
            '<p class="cqb-note">' .
            esc_html__(
                'All amounts are net, plus VAT. Term totals assume an unchanged scope.',
                'offerweave',
            ) .
            '</p></div>';
    }
}
