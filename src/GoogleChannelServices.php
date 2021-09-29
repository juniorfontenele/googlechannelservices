<?php
namespace JuniorFontenele\GoogleChannelServices;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Cloud\Channel\V1\CloudChannelServiceClient;
use Google\Cloud\Channel\V1\Customer;
use Google\Cloud\Channel\V1\Entitlement;
use Google\Cloud\Channel\V1\Parameter;
use Google\Cloud\Channel\V1\Product;
use Google\Cloud\Channel\V1\Sku;

class GoogleChannelServices {

  protected $serviceAccountJsonFile;
  protected $channelServicesAdminAccount;
  protected $apiClient;
  protected $accountId;

  public function __construct(string $serviceAccountJsonFile, string $channelServicesAdminAccount, string $channelServicesAccountId)
  {
    $this->serviceAccountJsonFile = $serviceAccountJsonFile;
    $this->channelServicesAdminAccount = $channelServicesAdminAccount;
    $credentials = new ServiceAccountCredentials(
      'https://www.googleapis.com/auth/apps.order',
      $this->serviceAccountJsonFile,
      $this->channelServicesAdminAccount
    );
    $this->apiClient = new CloudChannelServiceClient([
      'credentials' => $credentials
    ]);
    $this->accountId = $channelServicesAccountId;
  }

  public function getCustomers(): array {
    $customers = $this->apiClient->listCustomers('accounts/'.$this->accountId);
    $customersArray = [];
    /**
     * @var Customer $customer
     */
    foreach ($customers as $customer) {
      $name = $customer->getName();
      $nameArray = explode('/', $name);
      $id = $nameArray[count($nameArray) - 1];
      $customersArray[] = [
        'id' => $id,
        'name' => $name,
        'domain' => $customer->getDomain(),
        'cloudIdentityId' => $customer->getCloudIdentityId(),
        'orgDisplayName' => $customer->getOrgDisplayName()
      ];
    }
    return $customersArray;
  }

  public function getCustomerByDomain(string $domain): array|bool {
    $customers = $this->getCustomers();
    foreach ($customers as $customer) {
      if ($customer['domain'] === $domain) {
        return $customer;
      }
    }
    return false;
  }

  public function getProducts(): array
  {
    $products = $this->apiClient->listProducts('accounts/'.$this->accountId);
    $productsArray = [];
    /**
     * @var Product $product
     */
    foreach ($products as $product) {
      $name = $product->getName();
      $nameArray = explode('/', $name);
      $id = $nameArray[count($nameArray) - 1];
      $productsArray[] = [
        'id' => $id,
        'name' => $name,
        'displayName' => $product->getMarketingInfo()->getDisplayName(),
        'image' => $product->getMarketingInfo()->getDefaultLogo()->getContent()
      ];
    }
    return $productsArray;
  }

  public function getProductById(string $productId): array|bool
  {
    $products = $this->getProducts();
    foreach ($products as $product) {
      if ($product['id'] === $productId) {
        return $product;
      }
    }
    return false;
  }

  public function getProductSkus(string $productId): array
  {
    $skusArray = [];
    $products = $this->getProducts();
    foreach ($products as $product) {
      if ($product['id'] === $productId) {
        $skus = $this->apiClient->listSkus('products/'.$productId,'accounts/'.$this->accountId);
        /**
         * @var Sku $sku
         */
        foreach ($skus as $sku) {
          $name = $sku->getName();
          $nameArray = explode('/', $name);
          $id = $nameArray[count($nameArray) - 1];
          $skusArray[] = [
            'id' => $id,
            'name' => $name,
            'displayName' => $sku->getMarketingInfo()->getDisplayName(),
            'image' => $sku->getMarketingInfo()->getDefaultLogo()->getContent()
          ];
        }
      }
    }
    return $skusArray;
  }

  public function getProductSkuById(string $productId, string $skuId): array|bool
  {
    $skus = $this->getProductSkus($productId);
    foreach ($skus as $sku) {
      if ($sku['id'] === $skuId) {
        return $sku;
      }
    }
    return false;
  }

  public function getCustomerEntitlements(string $customerId): array
  {
    $entitlements = $this->apiClient->listEntitlements('accounts/'.$this->accountId.'/customers/'.$customerId);
    $entitlementsArray = [];
    /**
     * @var Entitlement $entitlement
     */
    foreach ($entitlements as $entitlement) {
      $name = $entitlement->getName();
      $nameArray = explode('/', $name);
      $id = $nameArray[count($nameArray) - 1];
      $parameters = [];
      /**
       * @var Parameter $parameter
       */
      foreach ($entitlement->getParameters() as $parameter) {
        switch ($parameter->getName()) {
          case 'num_units':
            $parameters['num_units'] = $parameter->getValue()->getInt64Value();
            break;
          case 'max_units':
            $parameters['max_units'] = $parameter->getValue()->getInt64Value();
            break;
          case 'assigned_units':
            $parameters['assigned_units'] = $parameter->getValue()->getInt64Value();
            break;  
        }
      }
      $productId = $entitlement->getProvisionedService()->getProductId();
      $product = $this->getProductById($productId);
      $skuId = $entitlement->getProvisionedService()->getSkuId();
      $sku = $this->getProductSkuById($productId, $skuId);
      $entitlementsArray[] = [
        'id' => $id,
        'name' => $name,
        'offer' => $entitlement->getOffer(),
        'createTime' => $entitlement->getCreateTime()->toDateTime(),
        'startTime' => $entitlement->getCommitmentSettings()->getStartTime()->toDateTime(),
        'endTime' => $entitlement->getCommitmentSettings()->getEndTime()->toDateTime(),
        'provisionedService' => [
          'id' => $entitlement->getProvisionedService()->getProvisioningId(),
          'productId' => $productId,
          'productName' => $product['displayName'],
          'skuId' => $skuId,
          'skuName' => $sku['displayName']
        ],
        'licenses' => $parameters
      ];
    }
    return $entitlementsArray;
  }
}