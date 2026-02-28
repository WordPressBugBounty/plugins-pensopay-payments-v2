import { test, expect } from '@playwright/test';

// @ts-ignore
test('payment', async ({ page }) => {
    //Go home
    await page.goto(process.env.SHOPDIR);

    //Find predefined product and add it
    await page.getByTestId(process.env.TEST_PRODUCT_ID).click();
    await page.waitForLoadState('networkidle');

    //Go to checkout and fill it
    await page.goto('/checkout/');
    await page.locator('#email').fill(process.env.CHECKOUT_EMAIL);
    await page.locator('#shipping-first_name').fill(process.env.CHECKOUT_FIRST);
    await page.locator('#shipping-last_name').fill(process.env.CHECKOUT_LAST);
    await page.locator('#shipping-address_1').fill(process.env.CHECKOUT_ADDR);
    await page.locator('#shipping-postcode').fill(process.env.CHECKOUT_POST);
    await page.locator('#shipping-city').fill(process.env.CHECKOUT_CITY);
    await page.locator('#shipping-phone').fill(process.env.CHECKOUT_TEL);
    await page.waitForLoadState('networkidle');
    await page.locator('#radio-control-wc-payment-method-options-pensopay_gateway').check();
    await page.waitForTimeout(2000); //New checkout has an ajax reload that doesn't work with networkidle
    // await page.locator('#terms').check();
    await page.locator('.wc-block-components-checkout-place-order-button').click();

    //Payment gateway step
    await page.locator('#card-number').fill('4000 1124 0117 2221');
    await page.locator('#card-expiration-date').fill('09 / 29');
    await page.locator('#card-cvv').fill('123');
    await page.getByRole('button', { name: 'Betal DKK' }).click();

    //Go home
    await page.waitForLoadState('networkidle')
});