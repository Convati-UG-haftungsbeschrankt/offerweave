<?php
namespace OfferWeave;

/** Free package implementation. All functionality in this file is available without an upgrade. */
final class EmailView
{
    public static function render(
        int $id,
        array $snapshot,
        string $channel = 'customer',
        ?array $email = null,
    ): array {
        if (!in_array($channel, ['customer', 'admin'], true)) {
            throw new \DomainException(__('Unknown email type.', 'offerweave')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Api email-preview catch or Mailer catch; no raw exception HTML.
        }
        return I18n::run(I18n::snapshot($snapshot), static function () use ($id, $snapshot, $channel) {
            if ($channel === 'customer') {
                $subject = sprintf(
                    Legal::binding($snapshot)
                        ? /* translators: 1: company or brand name, 2: saved request number. */
                        __('Your binding offer from %1$s – reference #%2$d', 'offerweave')
                        : /* translators: 1: company or brand name, 2: saved request number. */
                        __('Your price summary from %1$s – request #%2$d', 'offerweave'),
                    $snapshot['brand'],
                    $id,
                );
            } else {
                $subject = sprintf(
                    // translators: 1: company or brand name, 2: saved request number.
                    __('%1$s – new quote request #%2$d', 'offerweave'),
                    $snapshot['brand'],
                    $id,
                );
            }
            $subject = preg_replace('/[\r\n\x00-\x1f\x7f]+/', ' ', $subject);
            $html = Mailer::body($id, $snapshot);
            $text = preg_replace('/<\/(?:p|h[1-6]|tr|table|li)>|<br\s*\/?>/i', "\n", $html);
            $text = html_entity_decode(wp_strip_all_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return Legal::mail(['subject' => $subject, 'html' => $html, 'text' => trim($text)], $snapshot);
        });
    }
}
