import { defineConfig } from '@playwright/test';
import * as dotenv from 'dotenv';

dotenv.config();

export default defineConfig ({
    use: {
        headless: true,
        baseURL: process.env.URL,
        testIdAttribute: 'data-product_id',
        ignoreHTTPSErrors: true
    },
    workers: Number(process.env.WORKERS),
    repeatEach: Number(process.env.REPEAT_EACH),
    fullyParallel: true
});