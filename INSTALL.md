# Установка PLATEAM на 1С-Битрикс

Для разработчика интернет-магазина. Модуль рассчитан на **production**: `https://pla.team`.

## Требования

- 1С-Битрикс с модулем **sale**
- HTTPS на витрине
- От оператора ПЛАТИМ: `partner_code`, `api_key`; origin сайта в whitelist Системы

## 1. Установка файлов

**Вариант A — zip / Маркетплейс**

Установите решение `plateam.partner`. Компоненты попадут в `/local/components/plateam`.

**Вариант B — вручную**

```
modules/plateam.partner  →  /local/modules/plateam.partner
```

Админка → Marketplace → Установленные решения → **PLATEAM Partner** → Установить.

## 2. Настройки

**Настройки → Настройки модулей → PLATEAM Partner**

| Поле | Значение (production) |
| --- | --- |
| Код партнёра | выданный код |
| API key | боевой ключ (только server-side) |
| Origin платформы | `https://pla.team` |
| Base URL API | `https://pla.team/api/v0` |
| Origin go | `https://go.pla.team` |
| Referral token | токен вашей реф-ссылки (если есть) |
| Own / foreign promo | ваши промокоды Bitrix Sale (опционально) |

Нажмите **Проверить ключ**.

Песочница (по необходимости): замените origin/API на `https://app.demo.pla.team` и sandbox-ключ — только для теста, не для боя.

## 3. Шаблон сайта

В layout / footer:

```php
<?php $APPLICATION->IncludeComponent('plateam:widget', '', [], false); ?>
```

На странице корзины:

```php
<?php $APPLICATION->IncludeComponent('plateam:checkout', '', [], false); ?>
```

Виджет грузится с `{platform_origin}/widget.js`.

## 4. Поведение

- Плитки СЭС/УЭС в корзине → stash → свойства заказа `PLATEAM_*`
- Скидка = сертификаты; к оплате картой = cash
- Hold / paid / cancelled — события Sale, ключ только на сервере

См. [docs/partner-api-notes.md](docs/partner-api-notes.md).

## 5. Smoke

```bash
curl -sS -H "Authorization: Bearer <api_key>" \
  https://pla.team/api/v0/partners/me
```

1. Реферальный вход / промо → chip виджета  
2. Корзина → плитки сертификатов уменьшают «к оплате»  
3. Оплата → баланс в ПЛАТИМ обновился  

## Поддержка

[https://pla.team](https://pla.team) · пакет для Маркетплейса: [MARKETPLACE.md](MARKETPLACE.md)
