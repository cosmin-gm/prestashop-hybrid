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

namespace GlobalPayments\PaymentGatewayProvider\Platform\Admin;

use GlobalPayments\PaymentGatewayProvider\Gateways\AbstractGateway;
use GlobalPayments\PaymentGatewayProvider\PaymentMethods\AbstractPaymentMethod;
use GlobalPayments\PaymentGatewayProvider\Platform\Helper\EncryptionHelper;
use GlobalPayments\Api\Entities\Enums\ServiceEndpoints;
use PrestaShopBundle\Translation\TranslatorComponent as Translator;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ConfigForm
{
    /**
     * The cols for each form field.
     */
    public const FORM_COLS = 7;

    /**
     * @var \Context
     */
    protected $context;

    /**
     * @var string
     */
    protected $html = '';

    /**
     * @var \GlobalPayments
     */
    protected $module;

    /**
     * @var array
     */
    protected $paymentMethods;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var array
     */
    protected $postErrors = [];

    /**
     * Validation errors displayed next to their field, keyed by configuration key.
     *
     * @var array<string,string>
     */
    protected $fieldErrors = [];

    /**
     * Sanitized values of the submitted form, keyed by configuration key.
     *
     * @var array<string,string>
     */
    protected $submittedValues = [];

    /**
     * @var Translator
     */
    protected $translator;

    /**
     * Config Form constructor.
     *
     * @param \GlobalPayments $module
     */
    public function __construct(
        \GlobalPayments $module
    ) {
        $this->module = $module;

        $this->context = $this->module->getContext();
        $this->paymentMethods = $this->module->getPaymentMethods();
        $this->path = $this->module->getPath();
        $this->translator = $this->module->getTranslator();
    }

    /**
     * Get the content for the admin config form.
     *
     * @return string
     */
    public function getContent()
    {
        /*
         * Check for form submission. If a form has been submitted, validate it.
         */
        $this->validateForms();

        $this->html .= $this->getTemplate();

        return $this->html;
    }

    /**
     * Validate the admin config forms.
     *
     * @return void
     */
    protected function validateForms()
    {
        foreach ($this->paymentMethods as $paymentMethod) {
            if (\Tools::isSubmit('submit_' . $paymentMethod->id)) {
                $this->postErrors = $paymentMethod->validateAdminSettings();
                if ($this->hasSanitizedSettings($paymentMethod)) {
                    $this->submittedValues = $paymentMethod->getSanitizedAdminSettings();
                }
                if (!count($this->postErrors)) {
                    $this->postProcess($paymentMethod);
                } else {
                    foreach ($this->postErrors as $key => $error) {
                        $this->html .= $this->module->displayError($error);
                        if (is_string($key)) {
                            $this->fieldErrors[$key] = $error;
                        }
                    }
                }
            }
        }
    }

    /**
     * Save form data for a specific payment method.
     *
     * @param AbstractGateway|AbstractPaymentMethod $paymentMethod
     *
     * @return void
     */
    protected function postProcess($paymentMethod)
    {
        if ($this->hasSanitizedSettings($paymentMethod)) {
            $this->saveSanitizedSettings($paymentMethod);

            return;
        }

        foreach ($paymentMethod->formFields as $key => $formField) {
            if (isset($formField['multiple']) && $multipleKey = \Tools::getValue($key)) {
                $_POST[$key] = implode(',', $multipleKey);
            }
            if (\Tools::getIsset($key)) {
                \Configuration::updateValue($key, \Tools::getValue($key));
            }
        }

        $this->html .= $this->displayConfirmationMessage(
            $this->translator->trans('Settings updated', [], 'Modules.Globalpayments.Admin')
        );
    }

    /**
     * States whether the payment method sanitizes its own settings before they are saved.
     *
     * @param AbstractGateway|AbstractPaymentMethod $paymentMethod
     *
     * @return bool
     */
    protected function hasSanitizedSettings($paymentMethod)
    {
        return method_exists($paymentMethod, 'getSanitizedAdminSettings');
    }

    /**
     * Save the sanitized form data of a payment method. Fields flagged as 'encrypted' are encrypted first.
     *
     * @param AbstractGateway|AbstractPaymentMethod $paymentMethod
     *
     * @return void
     */
    protected function saveSanitizedSettings($paymentMethod)
    {
        $values = $paymentMethod->getSanitizedAdminSettings();
        $secrets = [];

        try {
            foreach ($values as $key => $value) {
                if (!empty($paymentMethod->formFields[$key]['encrypted'])) {
                    $secrets[] = $value;
                    $value = EncryptionHelper::encrypt($value);
                }
                if (!\Configuration::updateValue($key, $value)) {
                    throw new \PrestaShopException(sprintf('The "%s" setting could not be saved.', $key));
                }
            }
        } catch (\Throwable $e) {
            $this->logSaveError($paymentMethod, $e, $secrets);
            $this->html .= $this->module->displayError(
                $this->translator->trans(
                    'The settings could not be saved. Please check the logs for details.',
                    [],
                    'Modules.Globalpayments.Admin'
                )
            );

            return;
        }

        $this->html .= $this->displayConfirmationMessage(
            $this->translator->trans('Settings updated', [], 'Modules.Globalpayments.Admin')
        );
    }

    /**
     * Log an error that occurred while saving the settings. Secrets never reach the log.
     *
     * @param AbstractGateway|AbstractPaymentMethod $paymentMethod
     * @param \Throwable $error
     * @param string[] $secrets
     *
     * @return void
     */
    protected function logSaveError($paymentMethod, \Throwable $error, array $secrets)
    {
        \PrestaShopLogger::addLog(
            sprintf(
                'Global Payments: unable to save the %s settings: %s',
                $paymentMethod->id,
                EncryptionHelper::mask($error->getMessage(), $secrets)
            ),
            3,
            null,
            'Module',
            (int) $this->module->id,
            true
        );
    }

    /**
     * @param $message
     *
     * @return string
     */
    protected function displayConfirmationMessage($message)
    {
        $this->context->smarty->assign([
            'message' => $message,
        ]);

        return $this->module->display($this->path, 'views/templates/admin/display_confirmation.tpl');
    }

    /**
     * Returns the template for the admin config form.
     *
     * @return string
     */
    protected function getTemplate()
    {
        $forms = $this->generateForms();
        $formKeys = array_keys($forms);
        $firstKey = $formKeys[0];

        // Pass transaction endpoints to payment method when ID = globalpayments_ucp
        foreach ($this->paymentMethods as $method) {
            if ($method->id === 'globalpayments_ucp') {
                $method->transactionEndpoints = [
                    '%global_sandbox%' => ServiceEndpoints::GP_API_TEST,
                    '%global_prod%' => ServiceEndpoints::GP_API_PRODUCTION,
                    '%eu_sandbox%' => ServiceEndpoints::GP_API_TEST_EU,
                    '%eu_prod%' => ServiceEndpoints::GP_API_PRODUCTION_EU,
                ];
            }
        }

        $this->context->smarty->assign([
            'firstKey' => $firstKey,
            'forms' => $forms,
            'gateways' => $this->paymentMethods,
        ]);

        return $this->module->display($this->path, 'views/templates/admin/configure.tpl');
    }

    /**
     * Generate all the forms that will be displayed into the config page.
     *
     * @return array
     */
    protected function generateForms()
    {
        $forms = [];

        foreach ($this->paymentMethods as $paymentMethod) {
            $forms[$paymentMethod->id] = $this->renderForm($paymentMethod);
        }

        return $forms;
    }

    /**
     * Render the form for each payment method.
     *
     * @param AbstractGateway|AbstractPaymentMethod $paymentMethod
     * @return string
     */
    protected function renderForm($paymentMethod)
    {
        $formStructure = $this->generateFormStructure($paymentMethod->formFields, $paymentMethod->id);

        $this->context->smarty->assign([
            'formLegend' => $formStructure['legend'],
            'formInputs' => $formStructure['inputs'],
            'formSubmit' => $formStructure['submit'],
        ]);

        return $this->module->display($this->path, 'views/templates/admin/_partials/form.tpl');
    }

    /**
     * Create the structure of the form.
     *
     * @param array $formFields
     * @param string $id
     *
     * @return array
     */
    protected function generateFormStructure($formFields, $id)
    {
        return [
            'legend' => [
                'title' => $this->translator->trans('Settings', [], 'Modules.Globalpayments.Admin'),
                'icon' => 'icon-cogs',
            ],
            'inputs' => $this->generateFormInputs($formFields),
            'submit' => [
                'name' => 'submit_' . $id,
                'title' => $this->translator->trans('Save', [], 'Modules.Globalpayments.Admin'),
            ],
        ];
    }

    /**
     * Generate the inputs for the form.
     *
     * @param array $formFields
     *
     * @return array
     */
    protected function generateFormInputs($formFields)
    {
        $inputs = [];

        foreach ($formFields as $key => $value) {
            $currentConfig = [
                'type' => $value['type'] ?? '',
                'name' => $key,
                'value' => $this->getDisplayedValue($key, $value),
                'error' => $this->fieldErrors[$key] ?? '',
                'label' => $value['title'] ?? '',
                'desc' => $value['description'] ?? '',
                'class' => $value['class'] ?? '',
                'maxLength' => $value['maxLength'] ?? '',
                'default' => $value['default'] ?? '',
                'required' => $value['required'] ?? '',
                'title' => $value['title'] ?? '',
                'multiple' => $value['multiple'] ?? '',
                'disabled' => $value['disabled'] ?? '',
            ];

            switch ($value['type']) {
                case 'text':
                    $currentConfig['col'] = self::FORM_COLS;

                    break;
                case 'switch':
                    $currentConfig['is_bool'] = true;
                    $currentConfig['values'] = [
                        [
                            'value' => 1,
                            'label' => $this->translator->trans('Yes', [], 'Modules.Globalpayments.Admin'),
                        ],
                        [
                            'value' => 0,
                            'label' => $this->translator->trans('No', [], 'Modules.Globalpayments.Admin'),
                        ],
                    ];

                    break;
                case 'select':
                    $selectQuery = [];
                    $inputValue = $this->submittedValues[$key]
                        ?? \Tools::getValue($key, $this->getStoredValue($key, $value));
                    $selectedOptions = is_array($inputValue) ? $inputValue : explode(',', $inputValue);
                    foreach ($value['options'] as $optionKey => $optionValue) {
                        $selectQuery[] = [
                            'id_option' => $optionKey,
                            'name' => $optionValue,
                            'selected' => in_array($optionKey, $selectedOptions),
                        ];
                    }

                    $currentConfig['options'] = [
                        'query' => $selectQuery,
                        'id' => 'id_option',
                        'name' => 'name',
                    ];

                    break;
            }

            $inputs[] = $currentConfig;
        }

        return $inputs;
    }

    /**
     * Get the value displayed in a form field: what was just submitted, otherwise what is stored.
     *
     * @param string $key
     * @param array $formField
     *
     * @return mixed
     */
    protected function getDisplayedValue($key, $formField)
    {
        if (array_key_exists($key, $this->submittedValues)) {
            return $this->submittedValues[$key];
        }

        return !empty(\Tools::getValue($key)) ? \Tools::getValue($key) : $this->getStoredValue($key, $formField);
    }

    /**
     * Get the stored value of a form field. Fields that were never saved (e.g. settings added
     * after the module was installed) fall back to their default value.
     *
     * @param string $key
     * @param array $formField
     *
     * @return mixed
     */
    protected function getStoredValue($key, $formField)
    {
        $value = \Configuration::get($key);

        if (!empty($formField['encrypted'])) {
            return EncryptionHelper::decrypt($value);
        }

        if (false === $value && empty($formField['skipConfigSave']) && isset($formField['default'])) {
            return is_array($formField['default']) ? implode(',', $formField['default']) : $formField['default'];
        }

        return $value;
    }

    /**
     * Get the URL for the Credentials Check endpoint.
     *
     * @return string
     */
    public function getCredentialsCheckUrl()
    {
        return $this->context->link::getUrlSmarty([
            'entity' => 'sf',
            'route' => 'globalpayments_credentials_check',
        ]);
    }
}
