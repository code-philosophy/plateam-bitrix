# Partner API — заметки для адаптера Bitrix

Модуль реализует обязанности интернет-магазина из ТЗ ПЛАТИМ на стороне Bitrix Sale. Полный контракт — Partner API v0 на платформе (`/api/v0`).

## Роли

| Кто | Что |
| --- | --- |
| Платформа (`pla.team`) | Виджет JS, сессия visitor, баланс, hold, списание, выпуск СЭС/УЭС |
| Модуль Bitrix | Ключ API на сервере, UI checkout, свойства заказа, события paid/cancelled |
| Партнёр | Credentials, whitelist origin витрины, вставка компонентов в шаблон |

## События Sale → API

| Событие Bitrix | Действие |
| --- | --- |
| `OnSaleOrderSaved` (и related) | stash → свойства → скидка → hold; auto-paid если cash=0 |
| Оплата заказа | `POST /orders/paid` с `cashKop` из свойства |
| Отмена | `POST /orders/cancelled` |

Auth: `Authorization: Bearer <partner_api_key>` (только server-side).

## Правило сумм

`orderTotal = cashKop + sesKop + uesKop`.

Скидка в заказе Bitrix = `sesKop + uesKop`; к оплате картой = `cashKop`.

## Виджет

`<script src="{platform_origin}/widget.js">` — файл на платформе. Origin задаётся в options модуля.

## Не путать с демо

На staging демо-витрины могут вызывать часть API из браузера. **Боевой ИМ:** ключ только в options модуля; hold/paid — из PHP модуля.
