# plateam-bitrix

Адаптер **1С-Битрикс** для системы **ПЛАТИМ** (PLATEAM): модуль `plateam.partner` и компоненты `plateam:*`.

Репозиторий закрытый. Поставка — копирование в `/local` (не Маркетплейс 1С-Битрикс).

Виджет (`widget.js`) **не входит** в этот репо: модуль подгружает его с origin платформы (`https://pla.team/widget.js` по умолчанию).

## Структура

```
modules/plateam.partner/   — API, события Sale, скидка, hold/paid
components/plateam/
  widget/                  — <script src="{platform}/widget.js">
  checkout/                — плитки СЭС/УЭС в корзине
INSTALL.md                 — пошаговая установка
docs/partner-api-notes.md  — кратко: что делает адаптер на стороне ИМ
```

## Быстрый старт

1. Скопировать `modules/plateam.partner` → `/local/modules/plateam.partner`
2. Скопировать `components/plateam` → `/local/components/plateam`
3. Админка → Marketplace → Установленные решения → **plateam.partner** → Установить
4. Настройки модуля: код партнёра, API key, origin `https://pla.team`, API `https://pla.team/api/v0`
5. В шаблон: `plateam:widget`; в корзину: `plateam:checkout`
6. Кнопка «Проверить ключ» в настройках модуля

Подробности — в [INSTALL.md](INSTALL.md).

## Контуры

| Контур | Origin платформы | API |
| --- | --- | --- |
| **Production (дефолт)** | `https://pla.team` | `https://pla.team/api/v0` |
| Sandbox / staging | `https://app.demo.pla.team` | `https://app.demo.pla.team/api/v0` |

Ключ API — только на сервере ИМ (options модуля), не в публичном JS.

## Staging-only

Не использовать на боевом ИМ без необходимости:

- `install/sale_payment/plateam_stub` — тестовая оплата
- `tools/setup_demo_*`, `tools/debug_*`, `tools/test_*`, `tools/web_install.php`

Runtime endpoints, нужные продукту: `stash_checkout.php`, `pending_finish.php`, `promo_status.php`, `render_widget.php`, `retry_mark_paid.php`.

## Версия

См. `modules/plateam.partner/install/version.php` (сейчас `0.1.0` — партнёрский адаптер, не релиз Marketplace).
