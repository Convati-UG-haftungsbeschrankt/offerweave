<?php
namespace OfferWeave;

final class Mailer
{
    public static function summary(array $s): string
    {
        if (isset($s['tax'])) {
            return Tax::summaryText($s);
        }
        $rows = [];
        $prefix = $s['has_custom'] || $s['has_travel'] ? __('Known amounts –', 'offerweave') . ' ' : '';
        if ($s['priced_count']) {
            $rows[] =
                $prefix .
                (__('one-time:', 'offerweave') . ' ') .
                Currency::format($s['once_cents'], $s['currency'] ?? 'EUR') .
                (' ' . __('net', 'offerweave'));
            if ($s['monthly_cents'] || $s['terms']) {
                $rows[] =
                    __('Monthly:', 'offerweave') .
                    ' ' .
                    Currency::format($s['monthly_cents'], $s['currency'] ?? 'EUR') .
                    (' ' . __('net', 'offerweave'));
            }
        }
        foreach ($s['terms'] as $t) {
            $rows[] =
                __('Monthly services with a', 'offerweave') .
                ' ' .
                $t['months'] .
                (' ' . __('month term:', 'offerweave') . ' ') .
                Currency::format($t['total_cents'], $s['currency'] ?? 'EUR') .
                (' ' . __('net for this term', 'offerweave'));
        }
        if ($s['comparison_cents'] !== null) {
            $rows[] =
                __('For', 'offerweave') .
                ' ' .
                $s['comparison_months'] .
                (' ' .
                    _x(
                        'months including one-time services:',
                        'Original label: Monate einschließlich einmaliger Leistungen:',
                        'offerweave',
                    ) .
                    ' ') .
                Currency::format($s['comparison_cents'], $s['currency'] ?? 'EUR') .
                (' ' . __('net', 'offerweave'));
        }
        if ($s['has_custom']) {
            $rows[] = __('Additional items: individual price on request.', 'offerweave');
        }
        if ($s['has_travel']) {
            $rows[] = _x(
                'Outstanding travel costs will be added.',
                'Original label: Noch offene Reisekosten kommen hinzu.',
                'offerweave',
            );
        }
        return implode("\n", $rows);
    }
    public static function body(int $id, array $snapshot): string
    {
        return I18n::run(
            I18n::snapshot($snapshot),
            fn() => Currency::run(Currency::snapshot($snapshot), fn() => self::localizedBody($id, $snapshot)),
        );
    }
    private static function localizedBody(int $id, array $snapshot): string
    {
        $style = 'padding:12px;border-bottom:1px solid #dde3eb;text-align:left;vertical-align:top;';
        $html =
            '<html><body><div style="font-family:Arial,sans-serif;max-width:850px;color:#0d1426"><h1>' .
            esc_html($snapshot['brand']) .
            (' ' .
                (Legal::binding($snapshot)
                    ? esc_html__('– Binding offer #', 'offerweave')
                    : esc_html__('– Quote request #', 'offerweave'))) .
            (int) $id .
            ('</h1><h2>' . esc_html__('Contact details', 'offerweave') . '</h2>');
        foreach ($snapshot['fields'] as $f) {
            $html .=
                '<p><strong>' .
                esc_html($f['label']) .
                ':</strong><br>' .
                nl2br(esc_html($f['value'])) .
                '</p>';
        }
        $html .=
            '<h2>' .
            esc_html__('Your selection', 'offerweave') .
            '</h2><table style="width:100%;border-collapse:collapse"><thead><tr><th style="' .
            $style .
            ('">' . esc_html__('Selection', 'offerweave') . '</th><th style="') .
            $style .
            ('">' . esc_html_x('Scope', 'Original label: Umfang', 'offerweave') . '</th><th style="') .
            $style .
            ('">' .
                esc_html(
                    isset($snapshot['quote']['summary']['tax'])
                        ? __('Price and VAT', 'offerweave')
                        : __('Net price', 'offerweave'),
                ) .
                '</th></tr></thead><tbody>');
        foreach ($snapshot['quote']['items'] as $i) {
            $price =
                $i['amount_cents'] === null
                    ? __('On request', 'offerweave')
                    : Pricing::money(Tax::amount($i, 'amount_cents')) .
                        ($i['period'] === 'month'
                            ? ' ' . __('/ month', 'offerweave')
                            : ' ' . __('one-time', 'offerweave'));
            if ($i['period'] === 'month') {
                $price .=
                    "\n" .
                    $i['term_months'] .
                    (' ' . _x('month term', 'Original label: Monate Laufzeit', 'offerweave')) .
                    ($i['term_cents'] !== null
                        ? "\n" .
                            Pricing::money(Tax::amount($i, 'term_cents')) .
                            (' ' . __('for the term', 'offerweave'))
                        : '');
            }
            if (!empty($i['discount_cents'])) {
                $price =
                    __('Regular:', 'offerweave') .
                    ' ' .
                    Pricing::money(Tax::amount($i, 'regular_cents')) .
                    ('
' .
                        __('Promotional price:', 'offerweave') .
                        ' ') .
                    $price .
                    ('
' .
                        __('Savings:', 'offerweave') .
                        ' ') .
                    Pricing::money(Tax::amount($i, 'discount_cents')) .
                    ($i['period'] === 'month' ? ' ' . __('/ month', 'offerweave') : '');
            }
            if (isset($i['tax'])) {
                $price .= ' ' . $i['tax']['price_label'] . "\n" . Tax::lineText($i);
            }
            $html .=
                '<tr><td style="' .
                $style .
                '"><strong>' .
                esc_html($i['name']) .
                '</strong></td><td style="' .
                $style .
                '">' .
                nl2br(esc_html(implode("\n", array_merge($i['details'], $i['promotion_details'] ?? [])))) .
                '</td><td style="' .
                $style .
                '">' .
                nl2br(esc_html($price)) .
                '</td></tr>';
        }
        $html .=
            '</tbody></table><h2>' .
            esc_html__('Price summary', 'offerweave') .
            '</h2><p>' .
            nl2br(esc_html(self::summary($snapshot['quote']['summary']))) .
            ('</p><p>' .
                esc_html(Tax::note($snapshot['quote']['summary'], Legal::binding($snapshot))) .
                '</p><p>' .
                esc_html__('Price revision:', 'offerweave') .
                ' ') .
            esc_html(substr($snapshot['revision'], 0, 12)) .
            '</p></div></body></html>';
        return $html;
    }
    public static function send(int $id): bool
    {
        $row = Store::get($id);
        if (!$row || !Store::claimMail($id)) {
            return false;
        }
        $s = json_decode($row['payload'], true);
        if (!is_array($s)) {
            Store::mailResult($id, false);
            return false;
        }
        try {
            $e = $s['email'] ?? EmailConfig::snapshot(Config::get()['email']);
            $s['created_at'] ??= $row['created_at'];
            $message = EmailView::render($id, $s, 'admin', $e);
            $sender = Config::get()['email'];
            $sender['from_name'] = $sender['from_name'] ?: $s['brand'];
            $ok = MailTransport::send($row['mail_to'], $message, $sender, $row['contact_email']);
        } catch (\Throwable $e) {
            $ok = false;
        }
        Store::mailResult($id, (bool) $ok);
        return (bool) $ok;
    }
    public static function sendCustomer(int $id, bool $manual = false): bool
    {
        $row = Store::get($id);
        if (!$row || !Store::claimCustomerMail($id, $manual)) {
            return false;
        }
        if (!$manual) {
            $key = 'offerweave_customer_' . substr(Spam::sign(strtolower($row['contact_email'])), 0, 40);
            $limit = get_transient($key);
            if (is_array($limit) && $limit['count'] >= 6) {
                Store::customerMailResult(
                    $id,
                    'review',
                    __(
                        'Automatic sending to this address is temporarily limited. Please review and send manually if necessary.',
                        'offerweave',
                    ),
                );
                return false;
            }
            $limit = is_array($limit) ? $limit : ['count' => 0, 'until' => time() + 3600];
            ++$limit['count'];
            set_transient($key, $limit, max(1, $limit['until'] - time()));
        }
        try {
            $s = json_decode($row['payload'], true);
            if (!is_array($s)) {
                throw new \DomainException(__('Invalid saved request.', 'offerweave'));
            }
            $s['created_at'] ??= $row['created_at'];
            $e = $s['email'] ?? EmailConfig::snapshot(Config::get()['email']);
            $sender = Config::get()['email'];
            if (empty($sender['from_email'])) {
                throw new \DomainException(__('Sender is missing.', 'offerweave'));
            }
            $message = EmailView::render($id, $s, 'customer', $e);
            $sender['from_name'] = $sender['from_name'] ?: $s['brand'];
            $ok = MailTransport::send(
                $row['contact_email'],
                $message,
                $sender,
                $sender['reply_to'] ?: $sender['from_email'],
            );
        } catch (\Throwable $e) {
            $ok = false;
        }
        Store::customerMailResult(
            $id,
            $ok ? 'sent' : 'failed',
            $ok
                ? ''
                : __(
                    'The customer email could not be handed to the mail system. Please check the sender and delivery settings and try again.',
                    'offerweave',
                ),
        );
        return $ok;
    }
}
