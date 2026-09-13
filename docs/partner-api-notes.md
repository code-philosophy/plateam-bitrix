# Partner API — заметки для адаптера Bitrix

Модуль закрывает обязанности ИМ на Bitrix Sale. Контракт API: Partner API v0 на `https://pla.team/api/v0`.

## Роли

| Кто | Что |
| --- | --- |
| Платформа | Виджет JS, visitor, баланс, hold, списание, выпуск СЭС/УЭС |
| Модуль | Ключ на сервере, UI checkout, свойства заказа, paid/cancelled |
| Партнёр | Credentials, whitelist origin, компоненты в шаблоне |

## События Sale → API

| Событие | Действие |
| --- | --- |
| Сохранение заказа | stash → свойства → скидка → hold |
| Оплата | `POST /orders/paid` |
| Отмена | `POST /orders/cancelled` |

Auth: `Authorization: Bearer <partner_api_key>` только server-side.

## Суммы

`orderTotal = cashKop + sesKop + uesKop`.

## Виджет

`<script src="{platform_origin}/widget.js">` — файл на платформе ПЛАТИМ.
