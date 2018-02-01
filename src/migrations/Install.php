<?php

namespace craft\commerce\migrations;

use craft\commerce\elements\Order;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\gateways\Dummy;
use craft\commerce\Plugin;
use craft\commerce\records\Country;
use craft\commerce\records\Gateway;
use craft\commerce\records\OrderSettings;
use craft\commerce\records\OrderStatus;
use craft\commerce\records\PaymentCurrency;
use craft\commerce\records\Product as ProductRecord;
use craft\commerce\records\ProductType;
use craft\commerce\records\ProductTypeSite;
use craft\commerce\records\ShippingCategory;
use craft\commerce\records\ShippingMethod;
use craft\commerce\records\ShippingRule;
use craft\commerce\records\State;
use craft\commerce\records\TaxCategory;
use craft\commerce\records\Variant as VariantRecord;
use craft\db\ActiveRecord;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\DateTimeHelper;
use craft\helpers\ElementHelper;
use craft\helpers\Json;
use craft\helpers\MigrationHelper;
use craft\helpers\StringHelper;
use craft\records\Element;
use craft\records\Element_SiteSettings;
use craft\records\FieldLayout;
use craft\records\Plugin as PluginRecord;
use craft\records\Site;

/**
 * Installation Migration
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class Install extends Migration
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->createTables();
        $this->createIndexes();
        $this->addForeignKeys();
        $this->insertDefaultData();

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropForeignKeys();
        $this->dropTables();

        $this->delete('{{%elementindexsettings}}', ['type' => [Order::class, Product::class]]);

        return true;
    }

    // Protected Methods
    // =========================================================================

    /**
     * Creates the tables for Craft Commerce
     *
     * @return void
     */
    protected function createTables()
    {
        $this->createTable('{{%commerce_addresses}}', [
            'id' => $this->primaryKey(),
            'stockLocation' => $this->boolean()->notNull()->defaultValue(false),
            'attention' => $this->string(),
            'title' => $this->string(),
            'firstName' => $this->string()->notNull(),
            'lastName' => $this->string()->notNull(),
            'countryId' => $this->integer(),
            'stateId' => $this->integer(),
            'address1' => $this->string(),
            'address2' => $this->string(),
            'city' => $this->string(),
            'zipCode' => $this->string(),
            'phone' => $this->string(),
            'alternativePhone' => $this->string(),
            'businessName' => $this->string(),
            'businessTaxId' => $this->string(),
            'businessId' => $this->string(),
            'stateName' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_countries}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'iso' => $this->string(2)->notNull(),
            'stateRequired' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_customer_discountuses}}', [
            'id' => $this->primaryKey(),
            'discountId' => $this->integer()->notNull(),
            'customerId' => $this->integer()->notNull(),
            'uses' => $this->integer()->notNull()->unsigned(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_customers}}', [
            'id' => $this->primaryKey(),
            'userId' => $this->integer(),
            'lastUsedBillingAddressId' => $this->integer(),
            'lastUsedShippingAddressId' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_customers_addresses}}', [
            'id' => $this->primaryKey(),
            'customerId' => $this->integer()->notNull(),
            'addressId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_discount_purchasables}}', [
            'id' => $this->primaryKey(),
            'discountId' => $this->integer()->notNull(),
            'purchasableId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_discount_categories}}', [
            'id' => $this->primaryKey(),
            'discountId' => $this->integer()->notNull(),
            'categoryId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_discount_usergroups}}', [
            'id' => $this->primaryKey(),
            'discountId' => $this->integer()->notNull(),
            'userGroupId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_discounts}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'description' => $this->text(),
            'code' => $this->string(),
            'perUserLimit' => $this->integer()->notNull()->defaultValue(0)->unsigned(),
            'perEmailLimit' => $this->integer()->notNull()->defaultValue(0)->unsigned(),
            'totalUseLimit' => $this->integer()->notNull()->defaultValue(0)->unsigned(),
            'totalUses' => $this->integer()->notNull()->defaultValue(0)->unsigned(),
            'dateFrom' => $this->dateTime(),
            'dateTo' => $this->dateTime(),
            'purchaseTotal' => $this->integer()->notNull()->defaultValue(0),
            'purchaseQty' => $this->integer()->notNull()->defaultValue(0),
            'maxPurchaseQty' => $this->integer()->notNull()->defaultValue(0),
            'baseDiscount' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'perItemDiscount' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'percentDiscount' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'percentageOffSubject' => $this->enum('percentageOffSubject', ['original', 'discounted'])->notNull(),
            'excludeOnSale' => $this->boolean(),
            'freeShipping' => $this->boolean(),
            'allGroups' => $this->boolean(),
            'allPurchasables' => $this->boolean(),
            'allCategories' => $this->boolean(),
            'enabled' => $this->boolean(),
            'stopProcessing' => $this->boolean(),
            'sortOrder' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_emails}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'subject' => $this->string()->notNull(),
            'recipientType' => $this->enum('recipientType', ['customer', 'custom'])->defaultValue('custom'),
            'to' => $this->string(),
            'bcc' => $this->string(),
            'enabled' => $this->boolean(),
            'templatePath' => $this->string()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_gateways}}', [
            'id' => $this->primaryKey(),
            'type' => $this->string()->notNull(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'settings' => $this->text(),
            'paymentType' => $this->enum('paymentType', ['authorize', 'purchase'])->notNull()->defaultValue('purchase'),
            'frontendEnabled' => $this->boolean(),
            'sendCartInfo' => $this->boolean(),
            'isArchived' => $this->boolean(),
            'dateArchived' => $this->dateTime(),
            'sortOrder' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_lineitems}}', [
            'id' => $this->primaryKey(),
            'orderId' => $this->integer()->notNull(),
            'purchasableId' => $this->integer(),
            'options' => $this->text(),
            'optionsSignature' => $this->string()->notNull(),
            'price' => $this->decimal(14, 4)->notNull()->unsigned(),
            'saleAmount' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'salePrice' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'weight' => $this->decimal(14, 4)->notNull()->defaultValue(0)->unsigned(),
            'height' => $this->decimal(14, 4)->notNull()->defaultValue(0)->unsigned(),
            'length' => $this->decimal(14, 4)->notNull()->defaultValue(0)->unsigned(),
            'width' => $this->decimal(14, 4)->notNull()->defaultValue(0)->unsigned(),
            'total' => $this->decimal(14, 4)->notNull()->defaultValue(0)->unsigned(),
            'qty' => $this->integer()->notNull()->unsigned(),
            'note' => $this->text(),
            'snapshot' => $this->text()->notNull(),
            'taxCategoryId' => $this->integer()->notNull(),
            'shippingCategoryId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_orderadjustments}}', [
            'id' => $this->primaryKey(),
            'type' => $this->string()->notNull(),
            'name' => $this->string(),
            'description' => $this->string(),
            'amount' => $this->decimal(14, 4)->notNull(),
            'included' => $this->boolean(),
            'lineItemId' => $this->integer(),
            'sourceSnapshot' => $this->text()->notNull(),
            'orderId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_orderhistories}}', [
            'id' => $this->primaryKey(),
            'prevStatusId' => $this->integer(),
            'newStatusId' => $this->integer(),
            'orderId' => $this->integer()->notNull(),
            'customerId' => $this->integer()->notNull(),
            'message' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_orders}}', [
            'billingAddressId' => $this->integer(),
            'shippingAddressId' => $this->integer(),
            'gatewayId' => $this->integer(),
            'paymentSourceId' => $this->integer(),
            'customerId' => $this->integer(),
            'id' => $this->integer()->notNull(),
            'orderStatusId' => $this->integer(),
            'number' => $this->string(32),
            'couponCode' => $this->string(),
            'itemTotal' => $this->decimal(14, 4)->defaultValue(0),
            'totalPrice' => $this->decimal(14, 4)->defaultValue(0),
            'totalPaid' => $this->decimal(14, 4)->defaultValue(0),
            'email' => $this->string(),
            'isCompleted' => $this->boolean(),
            'dateOrdered' => $this->dateTime(),
            'datePaid' => $this->dateTime(),
            'currency' => $this->string(),
            'paymentCurrency' => $this->string(),
            'lastIp' => $this->string(),
            'orderLocale' => $this->char(12),
            'message' => $this->string(),
            'returnUrl' => $this->string(),
            'cancelUrl' => $this->string(),
            'shippingMethodHandle' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY(id)',
        ]);

        $this->createTable('{{%commerce_ordersettings}}', [
            'id' => $this->primaryKey(),
            'fieldLayoutId' => $this->integer(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_orderstatus_emails}}', [
            'id' => $this->primaryKey(),
            'orderStatusId' => $this->integer()->notNull(),
            'emailId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_orderstatuses}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'color' => $this->enum('color', ['green', 'orange', 'red', 'blue', 'yellow', 'pink', 'purple', 'turquoise', 'light', 'grey', 'black'])->notNull()->defaultValue('green'),
            'sortOrder' => $this->integer(),
            'default' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_paymentcurrencies}}', [
            'id' => $this->primaryKey(),
            'iso' => $this->string(3)->notNull(),
            'primary' => $this->boolean()->notNull()->defaultValue(false),
            'rate' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_paymentsources}}', [
            'id' => $this->primaryKey(),
            'userId' => $this->integer()->notNull(),
            'gatewayId' => $this->integer()->notNull(),
            'token' => $this->string()->notNull(),
            'description' => $this->string(),
            'response' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_products}}', [
            'id' => $this->integer()->notNull(),
            'typeId' => $this->integer(),
            'taxCategoryId' => $this->integer()->notNull(),
            'shippingCategoryId' => $this->integer()->notNull(),
            'postDate' => $this->dateTime(),
            'expiryDate' => $this->dateTime(),
            'promotable' => $this->boolean(),
            'freeShipping' => $this->boolean(),
            'defaultVariantId' => $this->integer(),
            'defaultSku' => $this->string(),
            'defaultPrice' => $this->decimal(14, 4),
            'defaultHeight' => $this->decimal(14, 4),
            'defaultLength' => $this->decimal(14, 4),
            'defaultWidth' => $this->decimal(14, 4),
            'defaultWeight' => $this->decimal(14, 4),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY(id)',
        ]);

        $this->createTable('{{%commerce_producttypes}}', [
            'id' => $this->primaryKey(),
            'fieldLayoutId' => $this->integer(),
            'variantFieldLayoutId' => $this->integer(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'hasDimensions' => $this->boolean(),
            'hasVariants' => $this->boolean(),
            'hasVariantTitleField' => $this->boolean(),
            'titleFormat' => $this->string()->notNull(),
            'skuFormat' => $this->string(),
            'descriptionFormat' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_producttypes_sites}}', [
            'id' => $this->primaryKey(),
            'productTypeId' => $this->integer()->notNull(),
            'siteId' => $this->integer()->notNull(),
            'uriFormat' => $this->text(),
            'template' => $this->string(500),
            'hasUrls' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_producttypes_shippingcategories}}', [
            'id' => $this->primaryKey(),
            'productTypeId' => $this->integer()->notNull(),
            'shippingCategoryId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_producttypes_taxcategories}}', [
            'id' => $this->primaryKey(),
            'productTypeId' => $this->integer()->notNull(),
            'taxCategoryId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_purchasables}}', [
            'id' => $this->integer()->notNull(),
            'sku' => $this->string()->notNull(),
            'price' => $this->decimal(14, 4)->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY(id)',
        ]);

        $this->createTable('{{%commerce_sale_purchasables}}', [
            'id' => $this->primaryKey(),
            'saleId' => $this->integer()->notNull(),
            'purchasableId' => $this->integer()->notNull(),
            'purchasableType' => $this->string()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_sale_categories}}', [
            'id' => $this->primaryKey(),
            'saleId' => $this->integer()->notNull(),
            'categoryId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_sale_usergroups}}', [
            'id' => $this->primaryKey(),
            'saleId' => $this->integer()->notNull(),
            'userGroupId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_sales}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'description' => $this->text(),
            'dateFrom' => $this->dateTime(),
            'dateTo' => $this->dateTime(),
            'discountType' => $this->enum('discountType', ['percent', 'flat'])->notNull(),
            'discountAmount' => $this->decimal(14, 4)->notNull(),
            'allGroups' => $this->boolean(),
            'allPurchasables' => $this->boolean(),
            'allCategories' => $this->boolean(),
            'enabled' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_shippingcategories}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'description' => $this->string(),
            'default' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_shippingmethods}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'enabled' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_shippingrule_categories}}', [
            'id' => $this->primaryKey(),
            'shippingRuleId' => $this->integer(),
            'shippingCategoryId' => $this->integer(),
            'condition' => $this->enum('condition', ['allow', 'disallow', 'require'])->notNull(),
            'perItemRate' => $this->decimal(14, 4),
            'weightRate' => $this->decimal(14, 4),
            'percentageRate' => $this->decimal(14, 4),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_shippingrules}}', [
            'id' => $this->primaryKey(),
            'shippingZoneId' => $this->integer(),
            'name' => $this->string()->notNull(),
            'description' => $this->string(),
            'methodId' => $this->integer()->notNull(),
            'priority' => $this->integer()->notNull()->defaultValue(0),
            'enabled' => $this->boolean(),
            'minQty' => $this->integer()->notNull()->defaultValue(0),
            'maxQty' => $this->integer()->notNull()->defaultValue(0),
            'minTotal' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'maxTotal' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'minWeight' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'maxWeight' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'baseRate' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'perItemRate' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'weightRate' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'percentageRate' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'minRate' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'maxRate' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_shippingzone_countries}}', [
            'id' => $this->primaryKey(),
            'shippingZoneId' => $this->integer()->notNull(),
            'countryId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_shippingzone_states}}', [
            'id' => $this->primaryKey(),
            'shippingZoneId' => $this->integer()->notNull(),
            'stateId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_shippingzones}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'description' => $this->string(),
            'countryBased' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_states}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'abbreviation' => $this->string(),
            'countryId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_taxcategories}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'description' => $this->string(),
            'default' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_taxrates}}', [
            'id' => $this->primaryKey(),
            'taxZoneId' => $this->integer()->notNull(),
            'taxCategoryId' => $this->integer()->notNull(),
            'name' => $this->string()->notNull(),
            'rate' => $this->decimal(14, 10)->notNull(),
            'include' => $this->boolean(),
            'isVat' => $this->boolean(),
            'taxable' => $this->enum('taxable', ['price', 'shipping', 'price_shipping', 'order_total_shipping', 'order_total_price'])->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_taxzone_countries}}', [
            'id' => $this->primaryKey(),
            'taxZoneId' => $this->integer()->notNull(),
            'countryId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_taxzone_states}}', [
            'id' => $this->primaryKey(),
            'taxZoneId' => $this->integer()->notNull(),
            'stateId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_taxzones}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'description' => $this->string(),
            'countryBased' => $this->boolean(),
            'default' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_transactions}}', [
            'id' => $this->primaryKey(),
            'parentId' => $this->integer(),
            'gatewayId' => $this->integer(),
            'userId' => $this->integer(),
            'hash' => $this->string(32),
            'type' => $this->enum('type', ['authorize', 'capture', 'purchase', 'refund'])->notNull(),
            'amount' => $this->decimal(14, 4),
            'paymentAmount' => $this->decimal(14, 4),
            'currency' => $this->string(),
            'paymentCurrency' => $this->string(),
            'paymentRate' => $this->decimal(14, 4),
            'status' => $this->enum('status', ['pending', 'redirect', 'success', 'failed', 'processing'])->notNull(),
            'reference' => $this->string(),
            'code' => $this->string(),
            'message' => $this->text(),
            'response' => $this->text(),
            'orderId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%commerce_variants}}', [
            'productId' => $this->integer(),
            'id' => $this->integer()->notNull(),
            'sku' => $this->string()->notNull(),
            'isDefault' => $this->boolean(),
            'price' => $this->decimal(14, 4)->notNull(),
            'sortOrder' => $this->integer(),
            'width' => $this->decimal(14, 4),
            'height' => $this->decimal(14, 4),
            'length' => $this->decimal(14, 4),
            'weight' => $this->decimal(14, 4),
            'stock' => $this->integer()->notNull()->defaultValue(0),
            'unlimitedStock' => $this->boolean(),
            'minQty' => $this->integer(),
            'maxQty' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY(id)',
        ]);
    }

    /**
     * Drop the tables
     *
     * @return void
     */
    protected function dropTables()
    {
        $this->dropTable('{{%commerce_addresses}}');
        $this->dropTable('{{%commerce_countries}}');
        $this->dropTable('{{%commerce_customer_discountuses}}');
        $this->dropTable('{{%commerce_customers}}');
        $this->dropTable('{{%commerce_customers_addresses}}');
        $this->dropTable('{{%commerce_discount_purchasables}}');
        $this->dropTable('{{%commerce_discount_categories}}');
        $this->dropTable('{{%commerce_discount_usergroups}}');
        $this->dropTable('{{%commerce_discounts}}');
        $this->dropTable('{{%commerce_emails}}');
        $this->dropTable('{{%commerce_gateways}}');
        $this->dropTable('{{%commerce_lineitems}}');
        $this->dropTable('{{%commerce_orderadjustments}}');
        $this->dropTable('{{%commerce_orderhistories}}');
        $this->dropTable('{{%commerce_orders}}');
        $this->dropTable('{{%commerce_ordersettings}}');
        $this->dropTable('{{%commerce_orderstatus_emails}}');
        $this->dropTable('{{%commerce_orderstatuses}}');
        $this->dropTable('{{%commerce_paymentcurrencies}}');
        $this->dropTable('{{%commerce_paymentsources}}');
        $this->dropTable('{{%commerce_products}}');
        $this->dropTable('{{%commerce_producttypes}}');
        $this->dropTable('{{%commerce_producttypes_sites}}');
        $this->dropTable('{{%commerce_producttypes_shippingcategories}}');
        $this->dropTable('{{%commerce_producttypes_taxcategories}}');
        $this->dropTable('{{%commerce_purchasables}}');
        $this->dropTable('{{%commerce_sale_purchasables}}');
        $this->dropTable('{{%commerce_sale_categories}}');
        $this->dropTable('{{%commerce_sale_usergroups}}');
        $this->dropTable('{{%commerce_sales}}');
        $this->dropTable('{{%commerce_shippingcategories}}');
        $this->dropTable('{{%commerce_shippingmethods}}');
        $this->dropTable('{{%commerce_shippingrule_categories}}');
        $this->dropTable('{{%commerce_shippingrules}}');
        $this->dropTable('{{%commerce_shippingzone_countries}}');
        $this->dropTable('{{%commerce_shippingzone_states}}');
        $this->dropTable('{{%commerce_shippingzones}}');
        $this->dropTable('{{%commerce_states}}');
        $this->dropTable('{{%commerce_taxcategories}}');
        $this->dropTable('{{%commerce_taxrates}}');
        $this->dropTable('{{%commerce_taxzone_countries}}');
        $this->dropTable('{{%commerce_taxzone_states}}');
        $this->dropTable('{{%commerce_taxzones}}');
        $this->dropTable('{{%commerce_transactions}}');
        $this->dropTable('{{%commerce_variants}}');

        return null;
    }

    /**
     * Creates the indexes.
     *
     * @return void
     */
    protected function createIndexes()
    {
        $this->createIndex($this->db->getIndexName('{{%commerce_addresses}}', 'countryId', false), '{{%commerce_addresses}}', 'countryId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_addresses}}', 'stateId', false), '{{%commerce_addresses}}', 'stateId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_countries}}', 'name', true), '{{%commerce_countries}}', 'name', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_countries}}', 'iso', true), '{{%commerce_countries}}', 'iso', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_customer_discountuses}}', 'customerId,discountId', true), '{{%commerce_customer_discountuses}}', 'customerId,discountId', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_customer_discountuses}}', 'discountId', false), '{{%commerce_customer_discountuses}}', 'discountId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_customers}}', 'userId', false), '{{%commerce_customers}}', 'userId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_customers_addresses}}', 'customerId,addressId', true), '{{%commerce_customers_addresses}}', 'customerId,addressId', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_customers_addresses}}', 'customerId', false), '{{%commerce_customers_addresses}}', 'customerId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_customers_addresses}}', 'addressId', false), '{{%commerce_customers_addresses}}', 'addressId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_discount_purchasables}}', 'discountId,purchasableId', true), '{{%commerce_discount_purchasables}}', 'discountId,purchasableId', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_discount_purchasables}}', 'purchasableId', false), '{{%commerce_discount_purchasables}}', 'purchasableId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_discount_categories}}', 'discountId,categoryId', true), '{{%commerce_discount_categories}}', 'discountId,categoryId', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_discount_categories}}', 'categoryId', false), '{{%commerce_discount_categories}}', 'categoryId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_discount_usergroups}}', 'discountId,userGroupId', true), '{{%commerce_discount_usergroups}}', 'discountId,userGroupId', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_discount_usergroups}}', 'userGroupId', false), '{{%commerce_discount_usergroups}}', 'userGroupId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_discounts}}', 'code', true), '{{%commerce_discounts}}', 'code', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_discounts}}', 'dateFrom', false), '{{%commerce_discounts}}', 'dateFrom', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_discounts}}', 'dateTo', false), '{{%commerce_discounts}}', 'dateTo', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_gateways}}', 'name', true), '{{%commerce_gateways}}', 'name', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_lineitems}}', 'orderId,purchasableId,optionsSignature', true), '{{%commerce_lineitems}}', 'orderId,purchasableId,optionsSignature', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_lineitems}}', 'purchasableId', false), '{{%commerce_lineitems}}', 'purchasableId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_lineitems}}', 'taxCategoryId', false), '{{%commerce_lineitems}}', 'taxCategoryId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_lineitems}}', 'shippingCategoryId', false), '{{%commerce_lineitems}}', 'shippingCategoryId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_orderadjustments}}', 'orderId', false), '{{%commerce_orderadjustments}}', 'orderId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_orderhistories}}', 'orderId', false), '{{%commerce_orderhistories}}', 'orderId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_orderhistories}}', 'prevStatusId', false), '{{%commerce_orderhistories}}', 'prevStatusId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_orderhistories}}', 'newStatusId', false), '{{%commerce_orderhistories}}', 'newStatusId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_orderhistories}}', 'customerId', false), '{{%commerce_orderhistories}}', 'customerId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_orders}}', 'number', false), '{{%commerce_orders}}', 'number', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_orders}}', 'billingAddressId', false), '{{%commerce_orders}}', 'billingAddressId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_orders}}', 'shippingAddressId', false), '{{%commerce_orders}}', 'shippingAddressId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_orders}}', 'gatewayId', false), '{{%commerce_orders}}', 'gatewayId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_orders}}', 'customerId', false), '{{%commerce_orders}}', 'customerId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_orders}}', 'orderStatusId', false), '{{%commerce_orders}}', 'orderStatusId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_ordersettings}}', 'handle', true), '{{%commerce_ordersettings}}', 'handle', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_ordersettings}}', 'fieldLayoutId', false), '{{%commerce_ordersettings}}', 'fieldLayoutId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_orderstatus_emails}}', 'orderStatusId', false), '{{%commerce_orderstatus_emails}}', 'orderStatusId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_orderstatus_emails}}', 'emailId', false), '{{%commerce_orderstatus_emails}}', 'emailId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_paymentcurrencies}}', 'iso', true), '{{%commerce_paymentcurrencies}}', 'iso', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_products}}', 'typeId', false), '{{%commerce_products}}', 'typeId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_products}}', 'postDate', false), '{{%commerce_products}}', 'postDate', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_products}}', 'expiryDate', false), '{{%commerce_products}}', 'expiryDate', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_products}}', 'taxCategoryId', false), '{{%commerce_products}}', 'taxCategoryId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_products}}', 'shippingCategoryId', false), '{{%commerce_products}}', 'shippingCategoryId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_producttypes}}', 'handle', true), '{{%commerce_producttypes}}', 'handle', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_producttypes}}', 'fieldLayoutId', false), '{{%commerce_producttypes}}', 'fieldLayoutId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_producttypes}}', 'variantFieldLayoutId', false), '{{%commerce_producttypes}}', 'variantFieldLayoutId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_producttypes_sites}}', 'productTypeId,siteId', true), '{{%commerce_producttypes_sites}}', 'productTypeId,siteId', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_producttypes_sites}}', 'siteId', false), '{{%commerce_producttypes_sites}}', 'siteId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_producttypes_shippingcategories}}', 'productTypeId,shippingCategoryId', true), '{{%commerce_producttypes_shippingcategories}}', 'productTypeId,shippingCategoryId', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_producttypes_shippingcategories}}', 'shippingCategoryId', false), '{{%commerce_producttypes_shippingcategories}}', 'shippingCategoryId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_producttypes_taxcategories}}', 'productTypeId,taxCategoryId', true), '{{%commerce_producttypes_taxcategories}}', 'productTypeId,taxCategoryId', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_producttypes_taxcategories}}', 'taxCategoryId', false), '{{%commerce_producttypes_taxcategories}}', 'taxCategoryId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_purchasables}}', 'sku', true), '{{%commerce_purchasables}}', 'sku', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_sale_purchasables}}', 'saleId,purchasableId', true), '{{%commerce_sale_purchasables}}', 'saleId,purchasableId', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_sale_purchasables}}', 'purchasableId', false), '{{%commerce_sale_purchasables}}', 'purchasableId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_sale_categories}}', 'saleId,categoryId', true), '{{%commerce_sale_categories}}', 'saleId,categoryId', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_sale_categories}}', 'categoryId', false), '{{%commerce_sale_categories}}', 'categoryId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_sale_usergroups}}', 'saleId,userGroupId', true), '{{%commerce_sale_usergroups}}', 'saleId,userGroupId', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_sale_usergroups}}', 'userGroupId', false), '{{%commerce_sale_usergroups}}', 'userGroupId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_shippingcategories}}', 'handle', true), '{{%commerce_shippingcategories}}', 'handle', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_shippingmethods}}', 'name', true), '{{%commerce_shippingmethods}}', 'name', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_shippingrule_categories}}', 'shippingRuleId', false), '{{%commerce_shippingrule_categories}}', 'shippingRuleId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_shippingrule_categories}}', 'shippingCategoryId', false), '{{%commerce_shippingrule_categories}}', 'shippingCategoryId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_shippingrules}}', 'name', false), '{{%commerce_shippingrules}}', 'name', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_shippingrules}}', 'methodId', false), '{{%commerce_shippingrules}}', 'methodId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_shippingrules}}', 'shippingZoneId', false), '{{%commerce_shippingrules}}', 'shippingZoneId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_shippingzone_countries}}', 'shippingZoneId,countryId', true), '{{%commerce_shippingzone_countries}}', 'shippingZoneId,countryId', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_shippingzone_countries}}', 'shippingZoneId', false), '{{%commerce_shippingzone_countries}}', 'shippingZoneId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_shippingzone_countries}}', 'countryId', false), '{{%commerce_shippingzone_countries}}', 'countryId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_shippingzone_states}}', 'shippingZoneId,stateId', true), '{{%commerce_shippingzone_states}}', 'shippingZoneId,stateId', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_shippingzone_states}}', 'shippingZoneId', false), '{{%commerce_shippingzone_states}}', 'shippingZoneId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_shippingzone_states}}', 'stateId', false), '{{%commerce_shippingzone_states}}', 'stateId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_shippingzones}}', 'name', true), '{{%commerce_shippingzones}}', 'name', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_states}}', 'name,countryId', true), '{{%commerce_states}}', 'name,countryId', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_states}}', 'countryId', false), '{{%commerce_states}}', 'countryId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_taxcategories}}', 'handle', true), '{{%commerce_taxcategories}}', 'handle', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_taxrates}}', 'taxZoneId', false), '{{%commerce_taxrates}}', 'taxZoneId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_taxrates}}', 'taxCategoryId', false), '{{%commerce_taxrates}}', 'taxCategoryId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_taxzone_countries}}', 'taxZoneId,countryId', true), '{{%commerce_taxzone_countries}}', 'taxZoneId,countryId', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_taxzone_countries}}', 'taxZoneId', false), '{{%commerce_taxzone_countries}}', 'taxZoneId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_taxzone_countries}}', 'countryId', false), '{{%commerce_taxzone_countries}}', 'countryId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_taxzone_states}}', 'taxZoneId,stateId', true), '{{%commerce_taxzone_states}}', 'taxZoneId,stateId', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_taxzone_states}}', 'taxZoneId', false), '{{%commerce_taxzone_states}}', 'taxZoneId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_taxzone_states}}', 'stateId', false), '{{%commerce_taxzone_states}}', 'stateId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_taxzones}}', 'name', true), '{{%commerce_taxzones}}', 'name', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_transactions}}', 'parentId', false), '{{%commerce_transactions}}', 'parentId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_transactions}}', 'gatewayId', false), '{{%commerce_transactions}}', 'gatewayId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_transactions}}', 'orderId', false), '{{%commerce_transactions}}', 'orderId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_transactions}}', 'userId', false), '{{%commerce_transactions}}', 'userId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_variants}}', 'sku', true), '{{%commerce_variants}}', 'sku', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_variants}}', 'productId', false), '{{%commerce_variants}}', 'productId', false);
    }

    /**
     * Adds the foreign keys.
     *
     * @return void
     */
    protected function addForeignKeys()
    {
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_addresses}}', 'countryId'), '{{%commerce_addresses}}', 'countryId', '{{%commerce_countries}}', 'id', 'SET NULL', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_addresses}}', 'stateId'), '{{%commerce_addresses}}', 'stateId', '{{%commerce_states}}', 'id', 'SET NULL', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_customer_discountuses}}', 'customerId'), '{{%commerce_customer_discountuses}}', 'customerId', '{{%commerce_customers}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_customer_discountuses}}', 'discountId'), '{{%commerce_customer_discountuses}}', 'discountId', '{{%commerce_discounts}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_customers}}', 'userId'), '{{%commerce_customers}}', 'userId', '{{%users}}', 'id', 'SET NULL', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_customers_addresses}}', 'addressId'), '{{%commerce_customers_addresses}}', 'addressId', '{{%commerce_addresses}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_customers_addresses}}', 'customerId'), '{{%commerce_customers_addresses}}', 'customerId', '{{%commerce_customers}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_discount_purchasables}}', 'discountId'), '{{%commerce_discount_purchasables}}', 'discountId', '{{%commerce_discounts}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_discount_purchasables}}', 'purchasableId'), '{{%commerce_discount_purchasables}}', 'purchasableId', '{{%commerce_purchasables}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_discount_categories}}', 'discountId'), '{{%commerce_discount_categories}}', 'discountId', '{{%commerce_discounts}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_discount_categories}}', 'categoryId'), '{{%commerce_discount_categories}}', 'categoryId', '{{%categories}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_discount_usergroups}}', 'discountId'), '{{%commerce_discount_usergroups}}', 'discountId', '{{%commerce_discounts}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_discount_usergroups}}', 'userGroupId'), '{{%commerce_discount_usergroups}}', 'userGroupId', '{{%usergroups}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_lineitems}}', 'orderId'), '{{%commerce_lineitems}}', 'orderId', '{{%commerce_orders}}', 'id', 'CASCADE', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_lineitems}}', 'purchasableId'), '{{%commerce_lineitems}}', 'purchasableId', '{{%elements}}', 'id', 'SET NULL', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_lineitems}}', 'shippingCategoryId'), '{{%commerce_lineitems}}', 'shippingCategoryId', '{{%commerce_shippingcategories}}', 'id', null, 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_lineitems}}', 'taxCategoryId'), '{{%commerce_lineitems}}', 'taxCategoryId', '{{%commerce_taxcategories}}', 'id', null, 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_orderadjustments}}', 'orderId'), '{{%commerce_orderadjustments}}', 'orderId', '{{%commerce_orders}}', 'id', 'CASCADE', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_orderhistories}}', 'customerId'), '{{%commerce_orderhistories}}', 'customerId', '{{%commerce_customers}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_orderhistories}}', 'newStatusId'), '{{%commerce_orderhistories}}', 'newStatusId', '{{%commerce_orderstatuses}}', 'id', null, 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_orderhistories}}', 'orderId'), '{{%commerce_orderhistories}}', 'orderId', '{{%commerce_orders}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_orderhistories}}', 'prevStatusId'), '{{%commerce_orderhistories}}', 'prevStatusId', '{{%commerce_orderstatuses}}', 'id', null, 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_orders}}', 'billingAddressId'), '{{%commerce_orders}}', 'billingAddressId', '{{%commerce_addresses}}', 'id', 'SET NULL', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_orders}}', 'customerId'), '{{%commerce_orders}}', 'customerId', '{{%commerce_customers}}', 'id', 'SET NULL', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_orders}}', 'id'), '{{%commerce_orders}}', 'id', '{{%elements}}', 'id', 'CASCADE', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_orders}}', 'orderStatusId'), '{{%commerce_orders}}', 'orderStatusId', '{{%commerce_orderstatuses}}', 'id', null, 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_orders}}', 'gatewayId'), '{{%commerce_orders}}', 'gatewayId', '{{%commerce_gateways}}', 'id', 'SET NULL', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_orders}}', 'paymentSourceId'), '{{%commerce_orders}}', 'paymentSourceId', '{{%commerce_paymentsources}}', 'id', 'SET NULL', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_orders}}', 'shippingAddressId'), '{{%commerce_orders}}', 'shippingAddressId', '{{%commerce_addresses}}', 'id', 'SET NULL', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_ordersettings}}', 'fieldLayoutId'), '{{%commerce_ordersettings}}', 'fieldLayoutId', '{{%fieldlayouts}}', 'id', 'SET NULL', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_orderstatus_emails}}', 'emailId'), '{{%commerce_orderstatus_emails}}', 'emailId', '{{%commerce_emails}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_orderstatus_emails}}', 'orderStatusId'), '{{%commerce_orderstatus_emails}}', 'orderStatusId', '{{%commerce_orderstatuses}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_paymentsources}}', 'gatewayId'), '{{%commerce_paymentsources}}', 'gatewayId', '{{%commerce_gateways}}', 'id', 'CASCADE', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_paymentsources}}', 'userId'), '{{%commerce_paymentsources}}', 'userId', '{{%users}}', 'id', 'CASCADE', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_products}}', 'id'), '{{%commerce_products}}', 'id', '{{%elements}}', 'id', 'CASCADE', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_products}}', 'shippingCategoryId'), '{{%commerce_products}}', 'shippingCategoryId', '{{%commerce_shippingcategories}}', 'id', null, null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_products}}', 'taxCategoryId'), '{{%commerce_products}}', 'taxCategoryId', '{{%commerce_taxcategories}}', 'id', null, null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_products}}', 'typeId'), '{{%commerce_products}}', 'typeId', '{{%commerce_producttypes}}', 'id', 'CASCADE', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_producttypes}}', 'fieldLayoutId'), '{{%commerce_producttypes}}', 'fieldLayoutId', '{{%fieldlayouts}}', 'id', 'SET NULL', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_producttypes}}', 'variantFieldLayoutId'), '{{%commerce_producttypes}}', 'variantFieldLayoutId', '{{%fieldlayouts}}', 'id', 'SET NULL', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_producttypes_sites}}', 'siteId'), '{{%commerce_producttypes_sites}}', 'siteId', '{{%sites}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_producttypes_sites}}', 'productTypeId'), '{{%commerce_producttypes_sites}}', 'productTypeId', '{{%commerce_producttypes}}', 'id', 'CASCADE', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_producttypes_shippingcategories}}', 'shippingCategoryId'), '{{%commerce_producttypes_shippingcategories}}', 'shippingCategoryId', '{{%commerce_shippingcategories}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_producttypes_shippingcategories}}', 'productTypeId'), '{{%commerce_producttypes_shippingcategories}}', 'productTypeId', '{{%commerce_producttypes}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_producttypes_taxcategories}}', 'productTypeId'), '{{%commerce_producttypes_taxcategories}}', 'productTypeId', '{{%commerce_producttypes}}', 'id', 'CASCADE', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_producttypes_taxcategories}}', 'taxCategoryId'), '{{%commerce_producttypes_taxcategories}}', 'taxCategoryId', '{{%commerce_taxcategories}}', 'id', 'CASCADE', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_purchasables}}', 'id'), '{{%commerce_purchasables}}', 'id', '{{%elements}}', 'id', 'CASCADE', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_sale_purchasables}}', 'purchasableId'), '{{%commerce_sale_purchasables}}', 'purchasableId', '{{%commerce_purchasables}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_sale_purchasables}}', 'saleId'), '{{%commerce_sale_purchasables}}', 'saleId', '{{%commerce_sales}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_sale_categories}}', 'categoryId'), '{{%commerce_sale_categories}}', 'categoryId', '{{%categories}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_sale_categories}}', 'saleId'), '{{%commerce_sale_categories}}', 'saleId', '{{%commerce_sales}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_sale_usergroups}}', 'saleId'), '{{%commerce_sale_usergroups}}', 'saleId', '{{%commerce_sales}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_sale_usergroups}}', 'userGroupId'), '{{%commerce_sale_usergroups}}', 'userGroupId', '{{%usergroups}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_shippingrule_categories}}', 'shippingCategoryId'), '{{%commerce_shippingrule_categories}}', 'shippingCategoryId', '{{%commerce_shippingcategories}}', 'id', 'CASCADE', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_shippingrule_categories}}', 'shippingRuleId'), '{{%commerce_shippingrule_categories}}', 'shippingRuleId', '{{%commerce_shippingrules}}', 'id', 'CASCADE', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_shippingrules}}', 'methodId'), '{{%commerce_shippingrules}}', 'methodId', '{{%commerce_shippingmethods}}', 'id', null, null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_shippingrules}}', 'shippingZoneId'), '{{%commerce_shippingrules}}', 'shippingZoneId', '{{%commerce_shippingzones}}', 'id', 'SET NULL', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_shippingzone_countries}}', 'countryId'), '{{%commerce_shippingzone_countries}}', 'countryId', '{{%commerce_countries}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_shippingzone_countries}}', 'shippingZoneId'), '{{%commerce_shippingzone_countries}}', 'shippingZoneId', '{{%commerce_shippingzones}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_shippingzone_states}}', 'shippingZoneId'), '{{%commerce_shippingzone_states}}', 'shippingZoneId', '{{%commerce_shippingzones}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_shippingzone_states}}', 'stateId'), '{{%commerce_shippingzone_states}}', 'stateId', '{{%commerce_states}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_states}}', 'countryId'), '{{%commerce_states}}', 'countryId', '{{%commerce_countries}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_taxrates}}', 'taxCategoryId'), '{{%commerce_taxrates}}', 'taxCategoryId', '{{%commerce_taxcategories}}', 'id', null, 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_taxrates}}', 'taxZoneId'), '{{%commerce_taxrates}}', 'taxZoneId', '{{%commerce_taxzones}}', 'id', null, 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_taxzone_countries}}', 'countryId'), '{{%commerce_taxzone_countries}}', 'countryId', '{{%commerce_countries}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_taxzone_countries}}', 'taxZoneId'), '{{%commerce_taxzone_countries}}', 'taxZoneId', '{{%commerce_taxzones}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_taxzone_states}}', 'stateId'), '{{%commerce_taxzone_states}}', 'stateId', '{{%commerce_states}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_taxzone_states}}', 'taxZoneId'), '{{%commerce_taxzone_states}}', 'taxZoneId', '{{%commerce_taxzones}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_transactions}}', 'orderId'), '{{%commerce_transactions}}', 'orderId', '{{%commerce_orders}}', 'id', 'CASCADE', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_transactions}}', 'parentId'), '{{%commerce_transactions}}', 'parentId', '{{%commerce_transactions}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_transactions}}', 'gatewayId'), '{{%commerce_transactions}}', 'gatewayId', '{{%commerce_gateways}}', 'id', null, 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_transactions}}', 'userId'), '{{%commerce_transactions}}', 'userId', '{{%users}}', 'id', 'SET NULL', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_variants}}', 'id'), '{{%commerce_variants}}', 'id', '{{%elements}}', 'id', 'CASCADE', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_variants}}', 'productId'), '{{%commerce_variants}}', 'productId', '{{%commerce_products}}', 'id', 'SET NULL', 'CASCADE');
    }

    /**
     * Adds the foreign keys.
     *
     * @return void
     */
    protected function dropForeignKeys()
    {
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_addresses}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_customer_discountuses}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_customers}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_customers_addresses}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_discount_purchasables}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_discount_categories}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_discount_usergroups}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_lineitems}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_orderadjustments}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_orderhistories}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_orders}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_ordersettings}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_orderstatus_emails}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_paymentsources}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_products}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_producttypes}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_producttypes_sites}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_producttypes_shippingcategories}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_producttypes_taxcategories}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_purchasables}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_sale_purchasables}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_sale_categories}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_sale_usergroups}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_shippingrule_categories}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_shippingrules}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_shippingzone_countries}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_shippingzone_states}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_states}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_taxrates}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_taxzone_countries}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_taxzone_states}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_transactions}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_variants}}', $this);
    }

    /**
     * Insert the default data.
     *
     * @return void
     */
    protected function insertDefaultData()
    {
        $this->_defaultCountries();
        $this->_defaultStates();
        $this->_defaultCurrency();
        $this->_defaultShippingMethod();
        $this->_defaultTaxCategories();
        $this->_defaultShippingCategories();
        $this->_defaultOrderSettings();
        $this->_defaultProductTypes();
        $this->_defaultProducts();
        $this->_defaultGateways();
        $this->_defaultSettings();
    }

    // Private Methods
    // =========================================================================

    /**
     * Insert default countries data.
     *
     * @return void
     */
    private function _defaultCountries()
    {
        $countries = [
            ['AD', 'Andorra'],
            ['AE', 'United Arab Emirates'],
            ['AF', 'Afghanistan'],
            ['AG', 'Antigua and Barbuda'],
            ['AI', 'Anguilla'],
            ['AL', 'Albania'],
            ['AM', 'Armenia'],
            ['AO', 'Angola'],
            ['AQ', 'Antarctica'],
            ['AR', 'Argentina'],
            ['AS', 'American Samoa'],
            ['AT', 'Austria'],
            ['AU', 'Australia'],
            ['AW', 'Aruba'],
            ['AX', 'Aland Islands'],
            ['AZ', 'Azerbaijan'],
            ['BA', 'Bosnia and Herzegovina'],
            ['BB', 'Barbados'],
            ['BD', 'Bangladesh'],
            ['BE', 'Belgium'],
            ['BF', 'Burkina Faso'],
            ['BG', 'Bulgaria'],
            ['BH', 'Bahrain'],
            ['BI', 'Burundi'],
            ['BJ', 'Benin'],
            ['BL', 'Saint Barthelemy'],
            ['BM', 'Bermuda'],
            ['BN', 'Brunei Darussalam'],
            ['BO', 'Bolivia'],
            ['BQ', 'Bonaire, Sint Eustatius and Saba'],
            ['BR', 'Brazil'],
            ['BS', 'Bahamas'],
            ['BT', 'Bhutan'],
            ['BV', 'Bouvet Island'],
            ['BW', 'Botswana'],
            ['BY', 'Belarus'],
            ['BZ', 'Belize'],
            ['CA', 'Canada'],
            ['CC', 'Cocos (Keeling) Islands'],
            ['CD', 'Democratic Republic of Congo'],
            ['CF', 'Central African Republic'],
            ['CG', 'Congo'],
            ['CH', 'Switzerland'],
            ['CI', 'Ivory Coast'],
            ['CK', 'Cook Islands'],
            ['CL', 'Chile'],
            ['CM', 'Cameroon'],
            ['CN', 'China'],
            ['CO', 'Colombia'],
            ['CR', 'Costa Rica'],
            ['CU', 'Cuba'],
            ['CV', 'Cape Verde'],
            ['CW', 'Curacao'],
            ['CX', 'Christmas Island'],
            ['CY', 'Cyprus'],
            ['CZ', 'Czech Republic'],
            ['DE', 'Germany'],
            ['DJ', 'Djibouti'],
            ['DK', 'Denmark'],
            ['DM', 'Dominica'],
            ['DO', 'Dominican Republic'],
            ['DZ', 'Algeria'],
            ['EC', 'Ecuador'],
            ['EE', 'Estonia'],
            ['EG', 'Egypt'],
            ['EH', 'Western Sahara'],
            ['ER', 'Eritrea'],
            ['ES', 'Spain'],
            ['ET', 'Ethiopia'],
            ['FI', 'Finland'],
            ['FJ', 'Fiji'],
            ['FK', 'Falkland Islands (Malvinas)'],
            ['FM', 'Micronesia'],
            ['FO', 'Faroe Islands'],
            ['FR', 'France'],
            ['GA', 'Gabon'],
            ['GB', 'United Kingdom'],
            ['GD', 'Grenada'],
            ['GE', 'Georgia'],
            ['GF', 'French Guiana'],
            ['GG', 'Guernsey'],
            ['GH', 'Ghana'],
            ['GI', 'Gibraltar'],
            ['GL', 'Greenland'],
            ['GM', 'Gambia'],
            ['GN', 'Guinea'],
            ['GP', 'Guadeloupe'],
            ['GQ', 'Equatorial Guinea'],
            ['GR', 'Greece'],
            ['GS', 'S. Georgia and S. Sandwich Isls.'],
            ['GT', 'Guatemala'],
            ['GU', 'Guam'],
            ['GW', 'Guinea-Bissau'],
            ['GY', 'Guyana'],
            ['HK', 'Hong Kong'],
            ['HM', 'Heard and McDonald Islands'],
            ['HN', 'Honduras'],
            ['HR', 'Croatia (Hrvatska)'],
            ['HT', 'Haiti'],
            ['HU', 'Hungary'],
            ['ID', 'Indonesia'],
            ['IE', 'Ireland'],
            ['IL', 'Israel'],
            ['IM', 'Isle Of Man'],
            ['IN', 'India'],
            ['IO', 'British Indian Ocean Territory'],
            ['IQ', 'Iraq'],
            ['IR', 'Iran'],
            ['IS', 'Iceland'],
            ['IT', 'Italy'],
            ['JE', 'Jersey'],
            ['JM', 'Jamaica'],
            ['JO', 'Jordan'],
            ['JP', 'Japan'],
            ['KE', 'Kenya'],
            ['KG', 'Kyrgyzstan'],
            ['KH', 'Cambodia'],
            ['KI', 'Kiribati'],
            ['KM', 'Comoros'],
            ['KN', 'Saint Kitts and Nevis'],
            ['KP', 'Korea (North)'],
            ['KR', 'Korea (South)'],
            ['KW', 'Kuwait'],
            ['KY', 'Cayman Islands'],
            ['KZ', 'Kazakhstan'],
            ['LA', 'Laos'],
            ['LB', 'Lebanon'],
            ['LC', 'Saint Lucia'],
            ['LI', 'Liechtenstein'],
            ['LK', 'Sri Lanka'],
            ['LR', 'Liberia'],
            ['LS', 'Lesotho'],
            ['LT', 'Lithuania'],
            ['LU', 'Luxembourg'],
            ['LV', 'Latvia'],
            ['LY', 'Libya'],
            ['MA', 'Morocco'],
            ['MC', 'Monaco'],
            ['MD', 'Moldova'],
            ['ME', 'Montenegro'],
            ['MF', 'Saint Martin (French part)'],
            ['MG', 'Madagascar'],
            ['MH', 'Marshall Islands'],
            ['MK', 'Macedonia'],
            ['ML', 'Mali'],
            ['MM', 'Burma (Myanmar)'],
            ['MN', 'Mongolia'],
            ['MO', 'Macau'],
            ['MP', 'Northern Mariana Islands'],
            ['MQ', 'Martinique'],
            ['MR', 'Mauritania'],
            ['MS', 'Montserrat'],
            ['MT', 'Malta'],
            ['MU', 'Mauritius'],
            ['MV', 'Maldives'],
            ['MW', 'Malawi'],
            ['MX', 'Mexico'],
            ['MY', 'Malaysia'],
            ['MZ', 'Mozambique'],
            ['NA', 'Namibia'],
            ['NC', 'New Caledonia'],
            ['NE', 'Niger'],
            ['NF', 'Norfolk Island'],
            ['NG', 'Nigeria'],
            ['NI', 'Nicaragua'],
            ['NL', 'Netherlands'],
            ['NO', 'Norway'],
            ['NP', 'Nepal'],
            ['NR', 'Nauru'],
            ['NU', 'Niue'],
            ['NZ', 'New Zealand'],
            ['OM', 'Oman'],
            ['PA', 'Panama'],
            ['PE', 'Peru'],
            ['PF', 'French Polynesia'],
            ['PG', 'Papua New Guinea'],
            ['PH', 'Philippines'],
            ['PK', 'Pakistan'],
            ['PL', 'Poland'],
            ['PM', 'St. Pierre and Miquelon'],
            ['PN', 'Pitcairn'],
            ['PR', 'Puerto Rico'],
            ['PS', 'Palestinian Territory, Occupied'],
            ['PT', 'Portugal'],
            ['PW', 'Palau'],
            ['PY', 'Paraguay'],
            ['QA', 'Qatar'],
            ['RE', 'Reunion'],
            ['RO', 'Romania'],
            ['RS', 'Republic of Serbia'],
            ['RU', 'Russia'],
            ['RW', 'Rwanda'],
            ['SA', 'Saudi Arabia'],
            ['SB', 'Solomon Islands'],
            ['SC', 'Seychelles'],
            ['SD', 'Sudan'],
            ['SE', 'Sweden'],
            ['SG', 'Singapore'],
            ['SH', 'St. Helena'],
            ['SI', 'Slovenia'],
            ['SJ', 'Svalbard and Jan Mayen Islands'],
            ['SK', 'Slovak Republic'],
            ['SL', 'Sierra Leone'],
            ['SM', 'San Marino'],
            ['SN', 'Senegal'],
            ['SO', 'Somalia'],
            ['SR', 'Suriname'],
            ['SS', 'South Sudan'],
            ['ST', 'Sao Tome and Principe'],
            ['SV', 'El Salvador'],
            ['SX', 'Sint Maarten (Dutch part)'],
            ['SY', 'Syria'],
            ['SZ', 'Swaziland'],
            ['TC', 'Turks and Caicos Islands'],
            ['TD', 'Chad'],
            ['TF', 'French Southern Territories'],
            ['TG', 'Togo'],
            ['TH', 'Thailand'],
            ['TJ', 'Tajikistan'],
            ['TK', 'Tokelau'],
            ['TL', 'Timor-Leste'],
            ['TM', 'Turkmenistan'],
            ['TN', 'Tunisia'],
            ['TO', 'Tonga'],
            ['TR', 'Turkey'],
            ['TT', 'Trinidad and Tobago'],
            ['TV', 'Tuvalu'],
            ['TW', 'Taiwan'],
            ['TZ', 'Tanzania'],
            ['UA', 'Ukraine'],
            ['UG', 'Uganda'],
            ['UM', 'United States Minor Outlying Islands'],
            ['US', 'United States'],
            ['UY', 'Uruguay'],
            ['UZ', 'Uzbekistan'],
            ['VA', 'Vatican City State (Holy See)'],
            ['VC', 'Saint Vincent and the Grenadines'],
            ['VE', 'Venezuela'],
            ['VG', 'Virgin Islands (British)'],
            ['VI', 'Virgin Islands (U.S.)'],
            ['VN', 'Viet Nam'],
            ['VU', 'Vanuatu'],
            ['WF', 'Wallis and Futuna Islands'],
            ['WS', 'Samoa'],
            ['YE', 'Yemen'],
            ['YT', 'Mayotte'],
            ['ZA', 'South Africa'],
            ['ZM', 'Zambia'],
            ['ZW', 'Zimbabwe'],
        ];

        $this->batchInsert('{{%commerce_countries}}', ['iso', 'name'], $countries);
    }

    /**
     * Add default States.
     *
     * @return void
     */
    private function _defaultStates()
    {
        $states = [
            'AU' => [
                'ACT' => 'Australian Capital Territory',
                'NSW' => 'New South Wales',
                'NT' => 'Northern Territory',
                'QLD' => 'Queensland',
                'SA' => 'South Australia',
                'TAS' => 'Tasmania',
                'VIC' => 'Victoria',
                'WA' => 'Western Australia',
            ],
            'CA' => [
                'AB' => 'Alberta',
                'BC' => 'British Columbia',
                'MB' => 'Manitoba',
                'NB' => 'New Brunswick',
                'NL' => 'Newfoundland and Labrador',
                'NT' => 'Northwest Territories',
                'NS' => 'Nova Scotia',
                'NU' => 'Nunavut',
                'ON' => 'Ontario',
                'PE' => 'Prince Edward Island',
                'QC' => 'Quebec',
                'SK' => 'Saskatchewan',
                'YT' => 'Yukon',
            ],
            'US' => [
                'AL' => 'Alabama',
                'AK' => 'Alaska',
                'AZ' => 'Arizona',
                'AR' => 'Arkansas',
                'CA' => 'California',
                'CO' => 'Colorado',
                'CT' => 'Connecticut',
                'DE' => 'Delaware',
                'DC' => 'District of Columbia',
                'FL' => 'Florida',
                'GA' => 'Georgia',
                'HI' => 'Hawaii',
                'ID' => 'Idaho',
                'IL' => 'Illinois',
                'IN' => 'Indiana',
                'IA' => 'Iowa',
                'KS' => 'Kansas',
                'KY' => 'Kentucky',
                'LA' => 'Louisiana',
                'ME' => 'Maine',
                'MD' => 'Maryland',
                'MA' => 'Massachusetts',
                'MI' => 'Michigan',
                'MN' => 'Minnesota',
                'MS' => 'Mississippi',
                'MO' => 'Missouri',
                'MT' => 'Montana',
                'NE' => 'Nebraska',
                'NV' => 'Nevada',
                'NH' => 'New Hampshire',
                'NJ' => 'New Jersey',
                'NM' => 'New Mexico',
                'NY' => 'New York',
                'NC' => 'North Carolina',
                'ND' => 'North Dakota',
                'OH' => 'Ohio',
                'OK' => 'Oklahoma',
                'OR' => 'Oregon',
                'PA' => 'Pennsylvania',
                'RI' => 'Rhode Island',
                'SC' => 'South Carolina',
                'SD' => 'South Dakota',
                'TN' => 'Tennessee',
                'TX' => 'Texas',
                'UT' => 'Utah',
                'VT' => 'Vermont',
                'VA' => 'Virginia',
                'WA' => 'Washington',
                'WV' => 'West Virginia',
                'WI' => 'Wisconsin',
                'WY' => 'Wyoming',
            ],
        ];

        /** @var ActiveRecord $countries */
        $countries = Country::find()->where(['in', 'iso', array_keys($states)])->all();
        $code2id = [];
        foreach ($countries as $record) {
            $code2id[$record->iso] = $record->id;
        }

        $rows = [];
        foreach ($states as $iso => $list) {
            foreach ($list as $abbr => $name) {
                $rows[] = [$code2id[$iso], $abbr, $name];
            }
        }

        $this->batchInsert(State::tableName(), ['countryId', 'abbreviation', 'name'], $rows);
    }

    /**
     * Make USD the default currency.
     *
     * @return void
     */
    private function _defaultCurrency()
    {
        $data = [
            'iso' => 'USD',
            'rate' => 1,
            'primary' => true
        ];
        $this->insert(PaymentCurrency::tableName(), $data);
    }

    /**
     * Add a default shipping method and rule.
     *
     * @return void
     */
    private function _defaultShippingMethod()
    {
        $data = [
            'name' => 'Free Shipping',
            'handle' => 'freeShipping',
            'enabled' => true
        ];
        $this->insert(ShippingMethod::tableName(), $data);

        $data = [
            'methodId' => $this->db->getLastInsertID(ShippingMethod::tableName()),
            'description' => 'All Countries, free shipping.',
            'name' => 'Free Everywhere',
            'enabled' => true
        ];
        $this->insert(ShippingRule::tableName(), $data);
    }

    /**
     * Add a default Tax category.
     *
     * @return void
     */
    private function _defaultTaxCategories()
    {
        $data = [
            'name' => 'General',
            'handle' => 'general',
            'default' => true
        ];
        $this->insert(TaxCategory::tableName(), $data);
    }

    /**
     * Add a default shipping category.
     *
     * @return void
     */
    private function _defaultShippingCategories()
    {
        $data = [
            'name' => 'General',
            'handle' => 'general',
            'default' => true
        ];
        $this->insert(ShippingCategory::tableName(), $data);
    }

    /**
     * Add the default order settings.
     *
     * @throws \Exception
     * @return void
     */
    private function _defaultOrderSettings()
    {
        $this->insert(FieldLayout::tableName(), ['type' => Order::class]);

        $data = [
            'name' => 'Order',
            'handle' => 'order',
            'fieldLayoutId' => $this->db->getLastInsertID(FieldLayout::tableName())
        ];
        $this->insert(OrderSettings::tableName(), $data);

        $data = [
            'name' => 'New',
            'handle' => 'new',
            'color' => 'green',
            'default' => true
        ];
        $this->insert(OrderStatus::tableName(), $data);

        $data = [
            'name' => 'Shipped',
            'handle' => 'shipped',
            'color' => 'blue',
            'default' => false
        ];
        $this->insert(OrderStatus::tableName(), $data);
    }

    /**
     * Set the default product types.
     *
     * @throws \Exception
     * @return void
     */
    private function _defaultProductTypes()
    {
        $this->insert(FieldLayout::tableName(), ['type' => Product::class]);
        $productFieldLayoutId = $this->db->getLastInsertID(FieldLayout::tableName());
        $this->insert(FieldLayout::tableName(), ['type' => Variant::class]);
        $variantFieldLayoutId = $this->db->getLastInsertID(FieldLayout::tableName());

        $data = [
            'name' => 'Clothing',
            'handle' => 'clothing',
            'hasDimensions' => true,
            'hasVariants' => false,
            'hasVariantTitleField' => false,
            'titleFormat' => '{product.title}',
            'fieldLayoutId' => $productFieldLayoutId,
            'variantFieldLayoutId' => $variantFieldLayoutId
        ];
        $this->insert(ProductType::tableName(), $data);
        $productTypeId = $this->db->getLastInsertID(ProductType::tableName());

        $siteIds = (new Query())
            ->select('id')
            ->from(Site::tableName())
            ->column();

        foreach ($siteIds as $siteId) {
            $data = [
                'productTypeId' => $productTypeId,
                'siteId' => $siteId,
                'uriFormat' => 'shop/products/{slug}',
                'template' => 'shop/products/_product',
                'hasUrls' => true
            ];
            $this->insert(ProductTypeSite::tableName(), $data);
        }
    }

    /**
     * Add some default products.
     *
     * @throws \Exception
     * @return void
     */
    private function _defaultProducts()
    {
        $productTypeId = (new Query())
            ->select('id')
            ->from(ProductType::tableName())
            ->scalar();

        $taxCategoryId = (new Query())
            ->select('id')
            ->from(TaxCategory::tableName())
            ->scalar();

        $shippingCategoryId = (new Query())
            ->select('id')
            ->from(ShippingCategory::tableName())
            ->scalar();

        if (!$productTypeId || !$taxCategoryId || !$shippingCategoryId) {
            throw new \RuntimeException('Cannot create the default products.');
        }

        $products = [
            'A New Toga',
            'Parka with Stripes on Back',
            'Romper for a Red Eye',
            'The Fleece Awakens'
        ];

        $count = 1;

        foreach ($products as $productName) {
            // Create an element for product
            $productElementData = [
                'type' => Product::class,
                'enabled' => 1,
                'archived' => 0
            ];
            $this->insert(Element::tableName(), $productElementData);
            $productId = $this->db->getLastInsertID(Element::tableName());

            // Create an element for variant
            $variantElementData = [
                'type' => Variant::class,
                'enabled' => 1,
                'archived' => 0
            ];
            $this->insert(Element::tableName(), $variantElementData);
            $variantId = $this->db->getLastInsertID(Element::tableName());

            // Populate the i18n data for each site
            $siteIds = (new Query())
                ->select('id')
                ->from(Site::tableName())
                ->column();

            foreach ($siteIds as $siteId) {
                // Product content data
                $productI18nData = [
                    'elementId' => $productId,
                    'siteId' => $siteId,
                    'slug' => ElementHelper::createSlug($productName),
                    'uri' => null,
                    'enabled' => true
                ];
                $this->insert(Element_SiteSettings::tableName(), $productI18nData);

                $contentData = [
                    'elementId' => $productId,
                    'siteId' => $siteId,
                    'title' => StringHelper::toTitleCase($productName)
                ];
                $this->insert('{{%content}}', $contentData);

                // Variant content data
                $variantI18nData = [
                    'elementId' => $variantId,
                    'siteId' => $siteId,
                    'slug' => ElementHelper::createSlug($productName),
                    'uri' => null,
                    'enabled' => true
                ];
                $this->insert(Element_SiteSettings::tableName(), $variantI18nData);

                $contentData = [
                    'elementId' => $variantId,
                    'siteId' => $siteId,
                    'title' => StringHelper::toTitleCase($productName)
                ];
                $this->insert('{{%content}}', $contentData);
            }

            $count++;

            // Prep data for variant and product
            $variantData = [
                'productId' => $productId,
                'id' => $variantId,
                'sku' => $productName,
                'price' => 10 * $count,
                'unlimitedStock' => true,
                'isDefault' => true
            ];

            $productData = [
                'id' => $productId,
                'typeId' => $productTypeId,
                'postDate' => DateTimeHelper::currentUTCDateTime()->format('Y-m-d H:i:s'),
                'expiryDate' => null,
                'promotable' => true,
                'defaultPrice' => 10 * $count,
                'defaultSku' => $productName,
                'taxCategoryId' => $taxCategoryId,
                'shippingCategoryId' => $shippingCategoryId,
            ];

            // Insert the actual product and variant
            $this->insert(ProductRecord::tableName(), $productData);
            $this->insert(VariantRecord::tableName(), $variantData);
        }
    }

    /**
     * Add a payment method.
     *
     * @return void
     */
    private function _defaultGateways()
    {
        $data = [
            'type' => Dummy::class,
            'name' => 'Dummy',
            'handle' => 'dummy',
            'settings' => Json::encode([]),
            'frontendEnabled' => true,
            'isArchived' => false,
        ];
        $this->insert(Gateway::tableName(), $data);
    }

    /**
     * Set default plugin settings.
     *
     * @return void
     */
    private function _defaultSettings()
    {
        $data = [
            'settings' => Json::encode([
                'orderPdfPath' => 'shop/_pdf/order',
                'orderPdfFilenameFormat' => 'Order-{number}'
            ])
        ];
        $this->update(PluginRecord::tableName(), $data, ['handle' => Plugin::getInstance()->handle]);
    }
}
