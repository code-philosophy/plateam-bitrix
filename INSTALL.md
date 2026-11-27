# Установка PLATEAM на 1С-Битрикс

Для разработчика интернет-магазина. Модуль рассчитан на **production**: `https://pla.team`.

Песочница и бой — **на одном URL**. Меняется только ключ из кабинета партнёра: `pk_test_…` (тест) → `pk_live_…` (бой). Origin/API на `app.demo.pla.team` менять не нужно (это внутренняя среда команды ПЛАТИМ, не онбординг партнёра).

## Требования

- PHP **7.4+**
- 1С-Битрикс **main ≥ 18.0.0** с модулем **sale**
- HTTPS на витрине
- От кабинета ПЛАТИМ (`https://pla.team`): `partner_code`, API key (`pk_test_` или `pk_live_`); origin сайта в whitelist Системы

## 1. Установка файлов

1. Скачать [plateam.partner.zip](https://github.com/code-philosophy/plateam-bitrix/releases/latest/download/plateam.partner.zip) (сборка с `main`, обновляется при каждом push).
2. Распаковать архив в `/local/modules/`. Внутри архива уже лежит папка `plateam.partner/`, поэтому путь на сайте будет `/local/modules/plateam.partner/`.
3. Админка → Marketplace → Установленные решения → **PLATEAM Partner** → Установить.

Компоненты при установке копируются в `/local/components/plateam`. Загрузка zip сама модуль не ставит: кнопка **Установить** регистрирует события и свойства заказа.

### Обновление (удаление → установка)

Удаление модуля **не** очищает настройки в `b_option` (код партнёра, промо и т.п. сохраняются). При повторной установке модуль сам:

- записывает фиксированные URL `https://pla.team`, `https://pla.team/api/v0`, `https://go.pla.team` (в настройках их нет);
- очищает устаревшие demo-ключи (не `pk_test_` / `pk_live_`) — вставьте актуальный ключ из ЛК;
- добавляет свойство заказа `PLATEAM_CHECKOUT_TOKEN` (нужно для hold после security-фиксов платформы).

После обновления обязательно нажмите **Проверить ключ**.

## 2. Настройки

**Настройки → Настройки модулей → PLATEAM Partner**

| Поле | Значение |
| --- | --- |
| Код партнёра | выданный код |
| API key | `pk_test_…` для проверки на сайте, затем `pk_live_…` для боя (только server-side) |
| Referral token | токен вашей реф-ссылки (если есть) |
| Own / foreign promo | ваши промокоды Bitrix Sale (опционально) |

Нажмите **Проверить ключ** — в ответе будет `keyEnv=sandbox` или `keyEnv=live`.

## 3. Шаблон сайта

В шапку сайта (`header.php` шаблона):

```php
<?php $APPLICATION->IncludeComponent('plateam:widget', '', [], false); ?>
```

На страницу корзины, которой реально пользуются покупатели (проверьте
`PATH_TO_BASKET` в `sale.order.ajax` — часто `/personal/cart/` и `/cart/` это
разные URL; компонент `plateam:checkout` нужен на **той**, где оформляют заказ):

```php
<?php $APPLICATION->IncludeComponent('plateam:checkout', '', [], false); ?>
```

На странице оформления заказа (`sale.order.ajax`), чтобы данные PLATEAM не
терялись при переходе с корзины:

```php
<?php $APPLICATION->IncludeComponent('plateam:checkout', '', ['MODE' => 'summary'], false); ?>
```

Виджет грузится с `https://pla.team/widget.js`, реф-ссылки — с `https://go.pla.team`. В сессии виджета **нет** API key — только публичные поля и короткоживущий `checkoutToken` для серверного `POST /holds`.

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

[https://pla.team](https://pla.team) · архив модуля: [plateam.partner.zip](https://github.com/code-philosophy/plateam-bitrix/releases/latest/download/plateam.partner.zip)
