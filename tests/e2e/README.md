# Pruebas E2E con Playwright

Este directorio contiene pruebas end-to-end (E2E) para la aplicación usando Playwright.

## Estructura de directorios

```
tests/e2e/
├── requests/         # Pruebas relacionadas con solicitudes de crédito
├── users/           # Pruebas relacionadas con usuarios
├── loans/          # Pruebas relacionadas con préstamos
└── common/         # Funciones utilitarias compartidas
```

## Instalación

1. Instalar Playwright globalmente:
```bash
npm install -g @playwright/test
```

2. Instalar los navegadores:
```bash
npx playwright install
```

## Ejecución de pruebas

### Modo interactivo (con ventana visible)
```bash
npx playwright test
```

### Modo headless (sin ventana)
```bash
npx playwright test --headless
```

### Ejecutar pruebas específicas
```bash
npx playwright test tests/e2e/requests/RequestCredit.test.js
```

### Generar reporte HTML
```bash
npx playwright test --reporter=html
```

Los reportes se generarán en la carpeta `tests/e2e/test-results/`
