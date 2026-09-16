# 21 BCN — Blackjack Barcelona

Blackjack minimalista inspirado en Barcelona, desarrollado con PHP, HTML, CSS, UIKit y jQuery. No utiliza base de datos: el estado vive únicamente en `$_SESSION`.

## Stack

- PHP 8.1+
- HTML5
- CSS propio + UIKit 3
- jQuery
- Sin base de datos

## Ejecutar en local

```bash
php -S localhost:8000
```

Abre `http://localhost:8000`.

## Funcionalidades actuales

- Repartir, pedir carta y plantarse.
- **Doblar apuesta**: duplica la apuesta de la mano activa, entrega una carta y planta automáticamente.
- **Dividir pareja / split** cuando las dos cartas iniciales tienen el mismo rango.
- Dos manos independientes después de dividir, con liquidación individual contra el crupier.
- Blackjack natural con pago 3:2.
- Selector de apuesta con botones `+ / -`, slider y apuestas rápidas de 5, 10, 25, 50 y 100 €.
- Saldo ficticio guardado en sesión.
- API AJAX en `api/game.php`.
- Interfaz responsive con UIKit y glassmorphism.
- 52 referencias barcelonesas, una por carta.
- Ilustraciones SVG generadas en frontend inspiradas en Sagrada Família, Eixample, Montjuïc, Arc de Triomf, mar Mediterráneo, trencadís, fachadas modernistas, panots y otros elementos de Barcelona.
- Un único reverso BCN compartido por toda la baraja.
- Atajos: `H` pedir, `S` plantarse, `D` doblar apuesta y `P` dividir pareja.

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

## Notas de reglas

Esta versión permite una división por ronda. El split requiere dos cartas con el mismo rango facial y saldo suficiente para colocar una segunda apuesta igual a la original. Una mano de 21 obtenida tras dividir no se considera blackjack natural y paga como victoria normal.
