# plateam-bitrix

Модуль **1С-Битрикс** для системы **ПЛАТИМ** (`plateam.partner` + компоненты `plateam:*`).

Подключает интернет-магазин к production-платформе [`https://pla.team`](https://pla.team): виджет сертификатов, apply в корзине, hold/paid через Partner API.

| | |
| --- | --- |
| Версия | **1.0.0** |
| MODULE_ID | `plateam.partner` |
| Репозиторий | private (Маркетплейс — загрузка пакета, см. [MARKETPLACE.md](MARKETPLACE.md)) |

## Быстрый старт

1. Скопировать `modules/plateam.partner` → `/local/modules/plateam.partner`  
   (или установить zip из `scripts/pack-marketplace.sh`)
2. Админка → **Marketplace → Установленные решения** → **PLATEAM Partner** → Установить  
   (компоненты копируются в `/local/components/plateam` автоматически)
3. **Настройки модулей → PLATEAM Partner:** код партнёра, API key, origin `https://pla.team`
4. В шаблон: `plateam:widget`; в корзину: `plateam:checkout`
5. Кнопка **Проверить ключ**

Подробно: [INSTALL.md](INSTALL.md).

## Структура

```
modules/plateam.partner/     ← пакет для Маркетплейса / ручной установки
  install/components/plateam/
  lib/  options.php  tools/  …
INSTALL.md
MARKETPLACE.md
devtools/                    ← только пилот east / отладка (не в zip)
scripts/pack-marketplace.sh
```

Виджет (`widget.js`) хостится на платформе: `{platform_origin}/widget.js`.

## Credentials

Ключ API и код партнёра выдаёт оператор ПЛАТИМ. Origin витрины должен быть в whitelist Системы. Ключ храните только в настройках модуля (server-side).
