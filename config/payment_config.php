<?php
/**
 * Payment and shipping configuration for MinC.
 *
 * Update the account details below before deploying to production.
 */

if (!function_exists('getMincPaymentConfig')) {
    function getMincPaymentConfig() {
        return [
            'shipping' => [
                'standard_fee' => 150.00,
                'free_threshold' => 1000.00,
                'coverage_label' => 'Angeles City, Pampanga',
                'coverage_note' => 'Shipping is currently available only within Angeles City, Pampanga. Delivery-app handling such as Grab or Lalamove is managed manually when needed.'
            ],
            'bpi' => [
                'enabled' => true,
                'label' => 'BPI Bank Transfer',
                'bank_name' => 'BPI',
                'account_name' => 'RITZMONCAR',
                'account_number' => 'Update BPI Account Number',
                'branch' => 'Update BPI Branch',
                'reference_label' => 'BPI Reference Number',
                'instructions' => 'Scan the BPI QR code or transfer to the BPI account shown below, then upload your proof of payment.',
                'qr_image' => 'Assets/images/payments/bpi-qr_new.png',
                'qr_link' => '',
                'qr_link_label' => 'Open BPI Payment Link'
            ],
            'gcash' => [
                'enabled' => true,
                'label' => 'GCash',
                'account_name' => 'RITZMONCAR',
                'account_number' => 'Update GCash Number',
                'reference_label' => 'GCash Reference Number',
                'instructions' => 'Scan the GCash QR code or send payment to the GCash number shown below, then upload your proof of payment.',
                'qr_image' => 'Assets/images/payments/gcash-qr_new.png',
                'qr_link' => '',
                'qr_link_label' => 'Open GCash Payment Link'
            ]
        ];
    }
}
