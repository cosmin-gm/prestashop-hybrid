<?php
/**
 * NOTICE OF LICENSE
 *
 * This file is licenced under the Software License Agreement.
 * With the purchase or the installation of the software in your application
 * you accept the licence agreement.
 *
 * You must not modify, adapt or create derivative works of this source code
 *
 * @author    GlobalPayments
 * @copyright Since 2021 GlobalPayments
 * @license   LICENSE
 */

namespace GlobalPayments\PaymentGatewayProvider\Gateways;

use GlobalPayments\Api\Entities\Enums\Environment;
use GlobalPayments\PaymentGatewayProvider\Requests\IntegrationType;
use GlobalPayments\PaymentGatewayProvider\Requests\TransactionType;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Worldpay Access gateway.
 *
 * Only the Back Office configuration tab is implemented for now. Worldpay terminology is used in the UI:
 * "Try" is the sandbox environment, "Username" is the App ID and "Password" is the App Key.
 */
class AccessWpGateway extends AbstractGateway
{
    /**
     * URL of the Worldpay Dashboard, where the merchant gets the credentials
     */
    public const DASHBOARD_URL = 'https://dashboard.worldpay.com/';

    /**
     * Gateway ID
     *
     * @var string
     */
    public $id = GatewayId::ACCESS_WP;

    /**
     * Admin title for the gateway
     *
     * @var string
     */
    public $adminTitle = 'Access WP';

    /**
     * Merchant entity
     *
     * @var string
     */
    public $merchantEntity;

    /**
     * Merchant narrative
     *
     * @var string
     */
    public $merchantNarrative;

    /**
     * Merchant description
     *
     * @var string
     */
    public $merchantDescription;

    /**
     * Should live payments be accepted
     *
     * @var bool
     */
    public $isProduction;

    /**
     * Live username
     *
     * @var string
     */
    public $username;

    /**
     * Live password
     *
     * @var string
     */
    public $password;

    /**
     * Try username
     *
     * @var string
     */
    public $sandboxUsername;

    /**
     * Try password
     *
     * @var string
     */
    public $sandboxPassword;

    /**
     * Should debug/logging be enabled
     *
     * @var bool
     */
    public $debug;

    public function getFirstLineSupportEmail()
    {
        return 'api.integrations@globalpay.com';
    }

    public function getFrontendGatewayOptions()
    {
        return [
            'env' => $this->isProduction ? parent::ENVIRONMENT_PRODUCTION : parent::ENVIRONMENT_SANDBOX,
        ];
    }

    public function getBackendGatewayOptions()
    {
        return [
            'username' => $this->getCredentialSetting('username'),
            'password' => $this->getCredentialSetting('password'),
            'merchantEntity' => $this->merchantEntity,
            'merchantNarrative' => $this->merchantNarrative,
            'merchantDescription' => $this->merchantDescription,
            'integrationMethod' => $this->integrationType,
            'environment' => $this->isProduction ? Environment::PRODUCTION : Environment::TEST,
            'debug' => $this->debug,
        ];
    }

    /**
     * {@inheritdoc}
     *
     * Unlike the other gateways, the form is fully defined here (no shared fields are appended),
     * and it is split into sections.
     */
    public function initFormFields()
    {
        $this->formFields = array_merge(
            $this->getSectionField(
                'sectionCommon',
                $this->translator->trans('Common Settings', [], 'Modules.Globalpayments.Admin')
            ),
            $this->getCommonFormFields(),
            $this->getSectionField(
                'sectionCredentials',
                $this->translator->trans('Environment & Credential Settings', [], 'Modules.Globalpayments.Admin')
            ),
            $this->getGatewayFormFields(),
            $this->getSectionField(
                'sectionOptions',
                $this->translator->trans('Gateway Options & Verification', [], 'Modules.Globalpayments.Admin')
            ),
            $this->getOptionsFormFields()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function configureMerchantSettings()
    {
        foreach ($this->formFields as $key => $options) {
            $attributeKey = str_replace($this->id . '_', '', $key);

            if (!empty($options['skipConfigSave']) || !property_exists($this, $attributeKey)) {
                continue;
            }

            $value = \Configuration::get($key);

            if ('switch' === $options['type']) {
                $value = '1' === $value;
            }

            $this->{$attributeKey} = $value;
        }

        if (empty($this->title)) {
            $this->title = $this->formFields[$this->id . '_title']['default'];
        }

        $this->integrationType = IntegrationType::HOSTED_PAYMENT_PAGE;
    }

    /**
     * Environment and credentials fields.
     *
     * {@inheritdoc}
     */
    public function getGatewayFormFields()
    {
        $credentialsDescription = $this->translator->trans(
            'Get your credentials from your <a href="%url%" target="_blank">Worldpay Dashboard</a>.',
            ['%url%' => self::DASHBOARD_URL],
            'Modules.Globalpayments.Admin'
        );
        $accountNameOptions = [
            'select_account' => $this->translator->trans('Select Account', [], 'Modules.Globalpayments.Admin'),
        ];

        return [
            $this->id . '_isProduction' => [
                'title' => $this->translator->trans('Live Mode', [], 'Modules.Globalpayments.Admin'),
                'type' => 'switch',
                'default' => 0,
            ],
            // Try fields
            $this->id . '_sandboxUsername' => [
                'title' => $this->translator->trans('Try Username', [], 'Modules.Globalpayments.Admin'),
                'type' => 'text',
                'class' => 'sandbox-toggle',
                'default' => '',
            ],
            $this->id . '_sandboxPassword' => [
                'title' => $this->translator->trans('Try Password', [], 'Modules.Globalpayments.Admin'),
                'type' => 'password',
                'class' => 'sandbox-toggle',
                'description' => $credentialsDescription,
                'default' => '',
            ],
            $this->id . '_sandboxAccountNameDropdown' => [
                'title' => $this->translator->trans('Try Account Name', [], 'Modules.Globalpayments.Admin'),
                'type' => 'select',
                'class' => 'sandbox-toggle',
                'default' => 'select_account',
                'skipConfigSave' => true,
                'options' => $accountNameOptions,
            ],
            // Live fields
            $this->id . '_username' => [
                'title' => $this->translator->trans('Live Username', [], 'Modules.Globalpayments.Admin'),
                'type' => 'text',
                'class' => 'live-toggle',
                'default' => '',
            ],
            $this->id . '_password' => [
                'title' => $this->translator->trans('Live Password', [], 'Modules.Globalpayments.Admin'),
                'type' => 'password',
                'class' => 'live-toggle',
                'description' => $credentialsDescription,
                'default' => '',
            ],
            $this->id . '_accountNameDropdown' => [
                'title' => $this->translator->trans('Live Account Name', [], 'Modules.Globalpayments.Admin'),
                'type' => 'select',
                'class' => 'live-toggle',
                'default' => 'select_account',
                'skipConfigSave' => true,
                'options' => $accountNameOptions,
            ],
        ];
    }

    /**
     * Payment processing is not implemented yet, so nothing is displayed at checkout.
     *
     * {@inheritdoc}
     */
    public function getPaymentOptions($module, $params, $isCheckout)
    {
        return [];
    }

    /**
     * Payment processing is not implemented yet, so no checkout scripts are needed.
     *
     * {@inheritdoc}
     */
    public function enqueuePaymentScripts($module)
    {
    }

    /**
     * Common settings fields.
     *
     * @return array
     */
    protected function getCommonFormFields()
    {
        return [
            $this->id . '_merchantEntity' => [
                'title' => $this->translator->trans('Merchant Entity', [], 'Modules.Globalpayments.Admin'),
                'type' => 'text',
                'default' => '',
            ],
            $this->id . '_merchantNarrative' => [
                'title' => $this->translator->trans('Merchant Narrative', [], 'Modules.Globalpayments.Admin'),
                'type' => 'text',
                'default' => '',
            ],
            $this->id . '_merchantDescription' => [
                'title' => $this->translator->trans('Merchant Description', [], 'Modules.Globalpayments.Admin'),
                'type' => 'text',
                'default' => '',
            ],
            $this->id . '_enabled' => [
                'title' => $this->translator->trans('Enable Gateway', [], 'Modules.Globalpayments.Admin'),
                'type' => 'switch',
                'default' => 0,
            ],
            $this->id . '_title' => [
                'title' => $this->translator->trans('Title', [], 'Modules.Globalpayments.Admin'),
                'type' => 'text',
                'description' => $this->translator->trans(
                    'This controls the title which the user sees during checkout.',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => $this->translator->trans('Worldpay Access', [], 'Modules.Globalpayments.Admin'),
            ],
            $this->id . '_paymentAction' => [
                'title' => $this->translator->trans('Payment Action', [], 'Modules.Globalpayments.Admin'),
                'type' => 'select',
                'description' => $this->translator->trans(
                    'Choose whether you wish to capture funds immediately or authorize payment only for a delayed capture.',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => TransactionType::SALE,
                'options' => [
                    TransactionType::SALE => $this->translator->trans(
                        'Authorize and Capture',
                        [],
                        'Modules.Globalpayments.Admin'
                    ),
                    TransactionType::AUTHORIZE => $this->translator->trans(
                        'Authorize Only',
                        [],
                        'Modules.Globalpayments.Admin'
                    ),
                ],
            ],
            $this->id . '_sortOrder' => [
                'title' => $this->translator->trans('Sort Order', [], 'Modules.Globalpayments.Admin'),
                'type' => 'number',
                'default' => 0,
            ],
        ];
    }

    /**
     * Gateway options and verification fields.
     *
     * @return array
     */
    protected function getOptionsFormFields()
    {
        return [
            $this->id . '_integrationType' => [
                'title' => $this->translator->trans('Integration Type', [], 'Modules.Globalpayments.Admin'),
                'type' => 'static',
                'default' => $this->translator->trans(
                    'Hosted Payment Page (HPP)',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'skipConfigSave' => true,
            ],
            $this->id . '_allowCardSaving' => [
                'title' => $this->translator->trans('Allow Card Saving', [], 'Modules.Globalpayments.Admin'),
                'type' => 'switch',
                'description' => $this->translator->trans(
                    'Note: to use the card saving feature, you must have multi-use token support enabled on your account. Please contact <a href="mailto:%s%?Subject=PrestaShop%%20Allow%%20Card%%20Saving">support</a> with any questions regarding this option.',
                    ['%s%' => $this->getFirstLineSupportEmail()],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => 0,
            ],
            $this->id . '_debug' => [
                'title' => $this->translator->trans('Enable Logging', [], 'Modules.Globalpayments.Admin'),
                'type' => 'switch',
                'description' => $this->translator->trans(
                    'Log all requests to and from gateway. This can also log private data and should only be enabled in a development or stage environment. Logs are saved to var/logs/ directory.',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => 0,
            ],
        ];
    }

    /**
     * Get a form entry that renders a section heading.
     *
     * @param string $name
     * @param string $title
     *
     * @return array
     */
    protected function getSectionField($name, $title)
    {
        return [
            $this->id . '_' . $name => [
                'title' => $title,
                'type' => 'section',
                'skipConfigSave' => true,
            ],
        ];
    }
}
