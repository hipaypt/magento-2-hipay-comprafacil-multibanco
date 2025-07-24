# HiPay Multibanco Gateway extension for Magento 2

## API credentials

HiPay API production or sandbox account credentials for each currency:
   - HiPay Comprafacil merchant login
   - HiPay Comprafacil merchant password
   - HiPay Comprafacil Multibanco Entity
   - Multibanco Reference validity in days

   
## Setup
    
  - Enabled: enable or disable extension
  - Sandbox: enable or disable sandbox account
  - Account credentials for sandbox or production account
  - Debug: log payment info
  - Multibanco Entity: entity for the sandbox or production account
  - Expiry date: Multibanco reference validity in days

  
## Requirements
  - SOAP extension


## Installation

Run
```console
composer require hipaypt/magento-2-hipay-comprafacil-multibanco
```
and then
```console
bin/magento module:enable --clear-static-content Hipay_HipayMultibancoGateway
bin/magento setup:upgrade
bin/magento cache:clean
```

To update
```console
composer update hipaypt/magento-2-hipay-comprafacil-multibanco
```
and then
```console
bin/magento setup:static-content:deploy
bin/magento setup:upgrade
bin/magento cache:clean
```

Version 1.0.4
