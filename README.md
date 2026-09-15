# 21 BCN — Blackjack Barcelona

Primera versión jugable de un blackjack minimalista inspirado en Barcelona.

## Stack

- PHP 8.1+ (backend y sesiones)
- HTML5
- CSS propio + UIKit 3
- jQuery
- Sin base de datos

## Ejecutar en local

```bash
php -S localhost:8000
```

Abre `http://localhost:8000`.

## Qué incluye esta V1

- Reglas funcionales de blackjack para un jugador contra crupier.
- Pedir, plantarse y doblar.
- Blackjack natural con pago 3:2.
- Saldo y apuesta guardados únicamente en `$_SESSION`.
- API AJAX en `api/game.php`.
- UI responsive con estética glass / Barcelona nocturna.
- 52 combinaciones de carta con referencia barcelonesa propia y patrón visual generativo único.
- Un único reverso BCN compartido por toda la baraja.
- Atajos de teclado: `H` pedir, `S` plantarse, `D` doblar.

## Estructura

```text
.
├── index.php
├── api/
│   └── game.php
├── src/
│   └── BlackjackGame.php
└── assets/
    ├── css/app.css
    └── js/game.js
```

## Siguiente iteración sugerida

La capa visual de las cartas está desacoplada de la lógica. Los 52 motivos viven en `BlackjackGame::motifs()` y cada carta recibe un `variant` único. Esto permite sustituir progresivamente los patrones generativos por SVGs/ilustraciones definitivas sin tocar el motor del juego.
