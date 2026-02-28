### Testing suite for pensopay Payments V2

#### First steps
1. Run npm install.
2. Copy .env.sample to .env and fill in the variables.

#### Running the tests
1. Run `./node_modules/.bin/playwright test` to run a test payment (make sure your shop is configured for payments in a test account).

#### Notes
- This assumes you are running latest woocommerce with the new checkout.
- If you want to see how it's running or debug the test, append `--headed` to the command.
- If you want more payments in parallel, adjust WORKERS in .env and make sure REPEAT_EACH is >= the workers.