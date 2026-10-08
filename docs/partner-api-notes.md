# Partner API — заметки для адаптера Bitrix

Модуль закрывает обязанности ИМ на Bitrix Sale. Контракт API: Partner API v0 на `https://pla.team/api/v0`.

Sandbox и live — тот же host; отличаются только ключи (`pk_test_` / `pk_live_`) и поле `keyEnv` в `GET /partners/me`.

## Роли

| Кто | Что |
| --- | --- |
| Платформа | Виджет JS, visitor, баланс, hold, списание, выпуск СЭС/УЭС |
| Модуль | Ключ на сервере, UI checkout, свойства заказа, hold/paid/cancelled |
| Партнёр | Credentials, whitelist origin, компоненты в шаблоне |

## События Sale → API

| Событие | Действие |
| --- | --- |
| Сохранение заказа | stash → свойства → скидка |
| Оплата | `POST /holds` (если есть сертификаты) → `POST /orders/paid` |
| Отмена | `POST /orders/cancelled` |

Auth Partner API: `Authorization: Bearer <partner_api_key>` только server-side.

### Hold (обязательный `checkoutToken`)

После закрытия утечки API key из публичной сессии виджета `POST /holds` требует короткоживущий `checkoutToken` (`wt_…`, TTL ~2 ч) из `PLATEAM.getSession()`.

Цепочка модуля:

1. checkout JS: `stashPayload` → `visitorId`, `userId`, суммы, **`checkoutToken`**
2. `stash_checkout.php` → PHP-сессия `PLATEAM_CHECKOUT`
3. свойства заказа, в т.ч. `PLATEAM_CHECKOUT_TOKEN`
4. `HoldService::createHold` → тело с `checkoutToken` + `operationId` / `Idempotency-Key`

Без токена hold не вызывается; в `PLATEAM_HOLD_ID` пишется маркер `E:missing_checkout_token` (или `E:…` при ответе API). Повторный hold возможен после появления валидного токена.

Paid/cancelled по-прежнему с сервера ИМ; виджетный `pay()` из браузера на боевом ИМ не используется.

## Суммы

`orderTotal = cashKop + sesKop + uesKop`.

## Виджет

`<script src="{platform_origin}/widget.js">` — файл на платформе ПЛАТИМ.  
В сессии нет `apiKey`; для серверного hold нужен `checkoutToken`.
