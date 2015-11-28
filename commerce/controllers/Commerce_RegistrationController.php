<?php
namespace Craft;

/**
 * Class Commerce_RegistrationController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_RegistrationController extends Commerce_BaseAdminController
{
    public function actionEdit()
    {
        $licenseKey = craft()->plugins->getPluginLicenseKey('Commerce');

        $this->renderTemplate('commerce/settings/registration', [
            'hasLicenseKey' => ($licenseKey !== null)
        ]);
    }

    public function actionGetLicenseInfo()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        craft()->et->ping();
        $this->_sendSuccessResponse();
    }

    public function actionUnregister()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $etResponse = craft()->et->unregisterPlugin('Commerce');
        $this->_handleEtResponse($etResponse);
    }

    public function actionUpdateLicenseKey()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $licenseKey = craft()->request->getRequiredPost('licenseKey');

        // Are we registering a new license key?
        if ($licenseKey) {
            // Record the license key locally
            try {
                craft()->plugins->setPluginLicenseKey('Commerce', $licenseKey);
            } catch (InvalidLicenseKeyException $e) {
                $this->returnErrorJson(Craft::t('That license key is invalid.'));
            }

            // Register it with Elliott
            $etResponse = craft()->et->registerPlugin('Commerce');
            $this->_handleEtResponse($etResponse);
        } else {
            // Just clear our record of the license key
            craft()->plugins->setPluginLicenseKey('Commerce', null);
            craft()->plugins->setPluginLicenseKeyStatus('Commerce', LicenseKeyStatus::Unknown);
            $this->_sendSuccessResponse();
        }
    }

    public function actionTransfer()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $etResponse = craft()->et->transferPlugin('Commerce');
        $this->_handleEtResponse($etResponse);
    }

    /**
     * Returns a response based on the EtService response.
     *
     * @return bool|string The resonse from EtService
     */
    private function _handleEtResponse($etResponse)
    {
        if (!empty($etResponse->data['success'])) {
            $this->_sendSuccessResponse();
        } else {
            $this->returnErrorJson(!empty($etResponse->errors) ? $etResponse->errors[0] : Craft::t('An unknown error occurred.'));
        }
    }

    /**
     * Returns a successful license update response.
     */
    private function _sendSuccessResponse()
    {
        $this->returnJson([
            'success' => true,
            'licenseKey' => craft()->plugins->getPluginLicenseKey('Commerce'),
            'licenseKeyStatus' => craft()->plugins->getPluginLicenseKeyStatus('Commerce'),
        ]);
    }
}
