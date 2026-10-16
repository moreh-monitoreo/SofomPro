import { test, expect } from '@playwright/test';

// Datos de prueba
const testData = {
    personal: {
        firstName: 'John',
        lastName: 'Doe',
        email: 'test@example.com',
        phone: '5551234567',
        birthDate: '1990-01-01'
    },
    address: {
        street: '123 Main St',
        city: 'City',
        state: 'State',
        zipCode: '12345'
    },
    civilInfo: {
        maritalStatus: 'single',
        dependents: '0'
    },
    employment: {
        employer: 'Company Inc',
        position: 'Developer',
        income: '50000'
    },
    loan: {
        amount: '10000',
        purpose: 'Personal',
        term: '24'
    }
};

test('should complete credit request form successfully', async ({ page }) => {
    // Navegar a la página de solicitud de crédito
    await page.goto('/prestamo-personal/solicitar');

    // Paso 1: Información Personal
    await page.fill('input[name="personal.firstName"]', testData.personal.firstName);
    await page.fill('input[name="personal.lastName"]', testData.personal.lastName);
    await page.fill('input[name="personal.email"]', testData.personal.email);
    await page.fill('input[name="personal.phone"]', testData.personal.phone);
    await page.fill('input[name="personal.birthDate"]', testData.personal.birthDate);
    await page.click('button:has-text("Siguiente")');

    // Paso 2: Información de Domicilio
    await page.fill('input[name="address.street"]', testData.address.street);
    await page.fill('input[name="address.city"]', testData.address.city);
    await page.fill('input[name="address.state"]', testData.address.state);
    await page.fill('input[name="address.zipCode"]', testData.address.zipCode);
    await page.click('button:has-text("Siguiente")');

    // Paso 3: Información Civil
    await page.selectOption('select[name="civilInfo.maritalStatus"]', testData.civilInfo.maritalStatus);
    await page.fill('input[name="civilInfo.dependents"]', testData.civilInfo.dependents);
    await page.click('button:has-text("Siguiente")');

    // Paso 4: Información de Empleo
    await page.fill('input[name="employment.employer"]', testData.employment.employer);
    await page.fill('input[name="employment.position"]', testData.employment.position);
    await page.fill('input[name="employment.income"]', testData.employment.income);
    await page.click('button:has-text("Siguiente")');

    // Paso 5: Información del Préstamo
    await page.fill('input[name="loan.amount"]', testData.loan.amount);
    await page.fill('input[name="loan.purpose"]', testData.loan.purpose);
    await page.fill('input[name="loan.term"]', testData.loan.term);
    await page.click('button:has-text("Siguiente")');

    // Paso 6: Finalización
    await expect(page).toHaveURL(/.*request-credit\/finished/);
    await expect(page.locator('h1')).toContainText('Solicitud completada');
});
