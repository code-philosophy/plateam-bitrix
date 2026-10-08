# Установка PLATEAM на 1С-Битрикс

Для разработчика интернет-магазина. Модуль рассчитан на **production**: `https://pla.team`.

Песочница и бой — **на одном URL**. Меняется только ключ из кабинета партнёра: `pk_test_…` (тест) → `pk_live_…` (бой). Origin/API на `app.demo.pla.team` менять не нужно (это внутренняя среда команды ПЛАТИМ, не онбординг партнёра).

## Требования

- 1С-Битрикс с модулем **sale**
- HTTPS на витрине
- От кабинета ПЛАТИМ (`https://pla.team`): `partner_code`, API key (`pk_test_` или `pk_live_`); origin сайта в whitelist Системы

## 1. Установка файлов

**Вариант A — zip / Маркетплейс**

Установите решение `plateam.partner`. Компоненты попадут в `/local/components/plateam`.

**Вариант B — вручную**

```
modules/plateam.partner  →  /local/modules/plateam.partner
```

Админка → Marketplace → Установленные решения → **PLATEAM Partner** → Установить.

### Обновление (удаление → установка)

Удаление модуля **не** очищает настройки в `b_option` (код партнёра, промо и т.п. сохраняются). При повторной установке модуль сам:

- переписывает унаследованные URL `*.demo.pla.team` → `https://pla.team` / `/api/v0` / `https://go.pla.team`;
- очищает устаревшие demo-ключи (не `pk_test_` / `pk_live_`) — вставьте актуальный ключ из ЛК;
- добавляет свойство заказа `PLATEAM_CHECKOUT_TOKEN` (нужно для hold после security-фиксов платформы).

После обновления обязательно нажмите **Проверить ключ**.

## 2. Настройки

**Настройки → Настройки модулей → PLATEAM Partner**

| Поле | Значение |
| --- | --- |
| Код партнёра | выданный код |
| API key | `pk_test_…` для проверки на сайте, затем `pk_live_…` для боя (только server-side) |
| Origin платформы | `https://pla.team` |
| Base URL API | `https://pla.team/api/v0` |
| Origin go | `https://go.pla.team` |
| Referral token | токен вашей реф-ссылки (если есть) |
| Own / foreign promo | ваши промокоды Bitrix Sale (опционально) |

Нажмите **Проверить ключ** — в ответе будет `keyEnv=sandbox` или `keyEnv=live`.

## 3. Шаблон сайта

В layout / footer:

```php
<?php $APPLICATION->IncludeComponent('plateam:widget', '', [], false); ?>
```

На странице корзины:

```php
<?php $APPLICATION->IncludeComponent('plateam:checkout', '', [], false); ?>
```

Виджет грузится с `{platform_origin}/widget.js`. В сессии виджета **нет** API key — только публичные поля и короткоживущий `checkoutToken` для серверного `POST /holds`.

## 4. Поведение

- Плитки СЭС/УЭС в корзине → stash (в т.ч. `checkoutToken`) → свойства заказа `PLATEAM_*`
- Скидка = сертификаты; к оплате картой = cash
- Hold / paid / cancelled — события Sale, ключ только на сервере; hold передаёт `checkoutToken` из сессии виджета

См. [docs/partner-api-notes.md](docs/partner-api-notes.md).

## 5. Smoke

```bash
curl -sS -H "Authorization: Bearer <api_key>" \
  https://pla.team/api/v0/partners/me
```

1. Реферальный вход / промо → chip виджета  
2. Корзина → плитки сертификатов уменьшают «к оплате»  
3. Оплата → баланс в ПЛАТИМ обновился (hold без `spend_unauthorized`)

## Поддержка

[https://pla.team](https://pla.team) · пакет для Маркетплейса: [MARKETPLACE.md](MARKETPLACE.md)
