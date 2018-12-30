# Stripe Connect
Direct amount transfer from one stripe/bank account to another one using custom code. Here i will explain how to use Stripe and PHP to transfer amount.

We need to use Stripe Connect to accept payments on behalf of others. In this scenario, we have to create customer for the buyer and connected account for the seller, then create charges to move funds from a buyer to a seller, with our platform optionally taking a transaction fee.

There are 3 approaches for processing payment on behalf of connected accounts. We are using deffered standard method for account activation. Also use an easiest way to create the charge on your platform account by using the destination parameter.

Here are the 4 steps iam going to follow

1. Create customer for buyer account
2. Connect seller with our platform
3. Create an account for seller, if a seller do't have stripe account. Otherwise get the existing account details of seller
4. Payment checkout directly from buyer account to seller account by deducting our platform fee.


Refer : https://stripe.com/docs/connect
