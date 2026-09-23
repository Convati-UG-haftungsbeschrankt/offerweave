<?php
namespace OfferWeave;

/** Shared local plan presentation; destinations are supplied by each distribution. */
abstract class PurchaseView
{
    abstract public static function checkoutUrl(int $sites): string;
    abstract public static function accountUrl(): string;

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'offerweave'));
        }
        $plans = [
            [1, 69, 5.75, __('For your website', 'offerweave')],
            [5, 129, 10.75, __('For several websites', 'offerweave')],
            [25, 249, 20.75, __('For your website portfolio', 'offerweave')],
        ];
        ?>
        <div class="wrap offerweave-purchase">
            <header class="offerweave-purchase-header">
                <div class="offerweave-purchase-brand">
                    <span class="offerweave-purchase-logo" aria-hidden="true"><img src="<?php echo esc_url(
                        OFFERWEAVE_URL . 'assets/brand/offerweave-logo.png',
                    ); ?>" alt="" width="48" height="48"></span>
                    <div><p class="offerweave-purchase-eyebrow">OfferWeave Pro</p><h1><?php esc_html_e(
                        'The right plan for your websites',
                        'offerweave',
                    ); ?></h1></div>
                </div>
                <a class="offerweave-purchase-account" href="<?php echo esc_url(
                    static::accountUrl(),
                ); ?>"><?php esc_html_e('My account', 'offerweave'); ?></a>
            </header>
            <?php // WordPress moves admin notices here instead of into the nested brand heading.
        ?>
            <hr class="wp-header-end">
            <p class="offerweave-purchase-intro"><?php esc_html_e(
                'All Pro features in every plan. Choose how many websites you want to register.',
                'offerweave',
            ); ?></p>
            <div class="offerweave-purchase-plans">
                <?php foreach ($plans as [$sites, $annual, $monthly, $description]):

                    /* translators: %d: Number of website registrations. */
                    $sitesLabel = sprintf(_n('%d website', '%d websites', $sites, 'offerweave'), $sites);
                    $monthlyLabel = sprintf(
                        /* translators: %s: Calculated monthly equivalent, including the euro symbol. */
                        __('Equivalent to %s per month', 'offerweave'),
                        number_format_i18n($monthly, 2) . ' €',
                    );
                    $checkoutLabel = sprintf(
                        /* translators: %d: Number of website registrations selected for checkout. */
                        _n(
                            'Checkout for %d website — opens a new tab',
                            'Checkout for %d websites — opens a new tab',
                            $sites,
                            'offerweave',
                        ),
                        $sites,
                    );
                    ?>
                    <article class="offerweave-purchase-plan" aria-labelledby="offerweave-plan-<?php echo esc_attr(
                        (string) $sites,
                    ); ?>">
                        <h2 id="offerweave-plan-<?php echo esc_attr((string) $sites); ?>"><?php echo esc_html(
    $sitesLabel,
); ?></h2>
                        <p class="offerweave-purchase-description"><?php echo esc_html($description); ?></p>
                        <p class="offerweave-purchase-price"><strong><?php echo esc_html(
                            number_format_i18n($annual, 0),
                        ); ?>&nbsp;€</strong> <span><?php esc_html_e('/ year', 'offerweave'); ?></span></p>
                        <p class="offerweave-purchase-vat"><?php esc_html_e(
                            'incl. 19% VAT',
                            'offerweave',
                        ); ?></p>
                        <p class="offerweave-purchase-monthly"><?php echo esc_html($monthlyLabel); ?></p>
                        <p class="offerweave-purchase-billing"><?php esc_html_e(
                            'Billed annually. Renews automatically.',
                            'offerweave',
                        ); ?></p>
                        <ul class="offerweave-purchase-benefits">
                            <li><?php esc_html_e('All OfferWeave Pro features', 'offerweave'); ?></li>
                            <li><?php esc_html_e('Official updates and downloads', 'offerweave'); ?></li>
                            <li><?php esc_html_e('Email support during the paid term', 'offerweave'); ?></li>
                        </ul>
                        <a class="offerweave-purchase-button" data-offerweave-checkout="<?php echo esc_attr(
                            (string) $sites,
                        ); ?>" href="<?php echo esc_url(
    static::checkoutUrl($sites),
); ?>" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer" aria-label="<?php echo esc_attr(
    $checkoutLabel,
); ?>"><?php esc_html_e('Go to checkout', 'offerweave'); ?> <span aria-hidden="true">↗</span></a>
                    </article>
                <?php
                endforeach; ?>
            </div>
            <p class="offerweave-purchase-tax"><?php esc_html_e(
                'Prices include 19% German VAT. Taxes are adjusted to your billing country at checkout.',
                'offerweave',
            ); ?></p>
            <section class="offerweave-purchase-faq" aria-labelledby="offerweave-purchase-faq">
                <h2 id="offerweave-purchase-faq"><?php esc_html_e(
                    'Questions about your subscription',
                    'offerweave',
                ); ?></h2>
                <details><summary><?php esc_html_e(
                    'Are updates included?',
                    'offerweave',
                ); ?></summary><p><?php esc_html_e(
    'Official updates, downloads and email support are included during the paid subscription term.',
    'offerweave',
); ?></p></details>
                <details><summary><?php esc_html_e(
                    'How do I cancel renewal?',
                    'offerweave',
                ); ?></summary><p><?php esc_html_e(
    'You can cancel automatic renewal through your customer account. Your subscription remains active until the end of the paid term.',
    'offerweave',
); ?></p></details>
            </section>
            <footer class="offerweave-purchase-footer">
                <span><?php esc_html_e('Sold and billed by Freemius, Inc.', 'offerweave'); ?></span>
                <a href="https://freemius.com/product/39020/offerweave/legal/eula/" target="_blank" rel="noopener noreferrer"><?php esc_html_e(
                    'Terms',
                    'offerweave',
                ); ?></a>
                <a href="https://customers.freemius.com/store/19833/login/" target="_blank" rel="noopener noreferrer"><?php esc_html_e(
                    'Customer portal',
                    'offerweave',
                ); ?></a>
            </footer>
        </div>
        <?php
    }
}
