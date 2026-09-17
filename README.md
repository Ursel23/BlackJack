# 21 BCN — Blackjack Barcelona

Blackjack minimalista inspirado en Barcelona, desarrollado con PHP, HTML, CSS, UIKit y jQuery. No utiliza base de datos: el estado vive en `$_SESSION`.

## Ejecutar en local

```bash
php -S localhost:8000
```

Abre `http://localhost:8000`.

## Funcionalidades

- Repartir, pedir carta y plantarse.
- Doblar apuesta: duplica la apuesta de la mano activa, entrega una carta y planta automáticamente.
- Split cuando las dos cartas iniciales tienen el mismo rango.
- En el split, la mano 1 conserva su apuesta y la apuesta de la mano 2 se puede elegir antes de separar las cartas.
- Selector de apuesta principal con `+ / -`, slider y apuestas rápidas.
- Blackjack natural con pago 3:2.
- Saldo ficticio guardado solo en sesión.
- Atajos: `H` pedir, `S` plantarse, `D` doblar y `P` dividir.
- Interfaz responsive con UIKit, glassmorphism y animaciones de reparto.

## Cartas PNG

El frontend intenta cargar primero las cartas reales desde `assets/cards/` siguiendo esta nomenclatura:

```text
spades-A.png ... spades-K.png
clubs-A.png ... clubs-K.png
hearts-A.png ... hearts-K.png
diamonds-A.png ... diamonds-K.png
back.png
```

Si un PNG todavía no existe, se usa automáticamente la carta HTML/CSS de fallback.

Hay un script en `tools/split_card_sheets.py` para separar las cuatro láminas de diseño en 52 caras y un único reverso común.

## Fondo de Barcelona

Guarda una fotografía horizontal con licencia adecuada como:

```text
assets/img/barcelona-bg.jpg
```

La capa `assets/css/v3.css` la muestra desenfocada y oscura. Si no existe, la web mantiene el fondo degradado.

## Estructura

```text
.
├── index.php
├── api/game.php
├── src/BlackjackGame.php
├── assets/
│   ├── favicon.svg
│   ├── cards/
│   ├── img/
│   ├── css/
│   │   ├── app.css
│   │   └── v3.css
│   └── js/game.js
└── tools/split_card_sheets.py
```
