# Установка PLATEAM на 1С-Битрикс

Аудитория: разработчик интернет-магазина партнёра (Bitrix + модуль **Sale**).

Общие правила интеграции с Системой ПЛАТИМ (виджет, hold/paid, сертификаты) описаны в ТЗ Приложения №2 к договору. Этот документ — только **адаптер Bitrix**: как поставить модуль и компоненты.

## Требования

- 1С-Битрикс с модулем **sale** (интернет-магазин)
- PHP как у целевого сайта Bitrix
- HTTPS на витрине
- Credentials от оператора ПЛАТИМ: `partner_code`, `api_key`, whitelist `site_origin` витрины в Системе

## 1. Файлы

Из этого репозитория:

```
modules/plateam.partner  →  /local/modules/plateam.partner
components/plateam       →  /local/components/plateam
```

Права — как у остальных `/local`.

## 2. Регистрация модуля

Админка → **Marketplace → Установленные решения** → **plateam.partner** → **Установить**.

Альтернатива (только стенд): `tools/web_install.php` — сидит demo-контур, на prod не использовать.

## 3. Настройки модуля

**Настройки → Настройки модулей → PLATEAM Partner**

| Поле | Production | Sandbox |
| --- | --- | --- |
| Код партнёра | выданный код | напр. `north` на демо |
| API key | боевой ключ (server-side) | sandbox-ключ |
| Origin платформы | `https://pla.team` | `https://app.demo.pla.team` |
| Base URL API | `https://pla.team/api/v0` | `https://app.demo.pla.team/api/v0` |

Дефолты модуля — **production** (`pla.team`). Для песочницы явно укажите demo.

Нажмите **Проверить ключ** — ожидается OK с кодом партнёра.

## 4. Вставка в шаблон

В общий шаблон сайта (footer или layout):

```php
<?php $APPLICATION->IncludeComponent('plateam:widget', '', [], false); ?>
```

На странице **корзины** / оформления (где нужна оплата сертификатами):

```php
<?php $APPLICATION->IncludeComponent('plateam:checkout', '', [], false); ?>
```

`plateam:checkout` берёт сумму из корзины Sale (`BasketTotal`). При необходимости передайте `ORDER_TOTAL_KOP`.

Виджет загружается как:

```text
{platform_origin}/widget.js
```

то есть с платформы ПЛАТИМ, не из файлов модуля.

## 5. Что делает модуль после установки

Кратко — [docs/partner-api-notes.md](docs/partner-api-notes.md).

- UI сертификатов в корзине → stash → свойства заказа
- Скидка на заказ = СЭС + УЭС; к оплате картой = cash
- Hold / `orders/paid` / `orders/cancelled` через события Sale (ключ только на сервере)

Свойства заказа: `PLATEAM_VISITOR_ID`, `PLATEAM_HOLD_ID`, `PLATEAM_SES_KOP`, `PLATEAM_UES_KOP`, `PLATEAM_CASH_KOP`.

## 6. Smoke

```bash
curl -sS -H "Authorization: Bearer <api_key>" \
  https://pla.team/api/v0/partners/me
```

На sandbox замените host на `app.demo.pla.team`.

### Чеклист visitor (W6)

1. Реферальный вход через платформу / `?pla_ref=`
2. Chip виджета на сайте ИМ
3. Тот же visitor id на другой витрине сети (если применимо)

### Чеклист apply (W4)

1. Реф → товар в корзину → оформление
2. Плитка СЭС/УЭС уменьшает «к оплате картой»
3. В заказе свойства `PLATEAM_*`; сумма платежа = cash
4. После оплаты — списание/выпуск в ПЛАТИМ, баланс обновился

## 7. Staging-only (не для боя)

- Платёжка `plateam_stub`, `tools/install_paysystem.php`
- `tools/setup_demo_*`, `tools/debug_*`, `tools/test_*`, `tools/web_install.php`

## Поддержка

Оператор системы: [https://pla.team](https://pla.team). Маркетплейс 1С-Битрикс для этого пакета пока не используется.
