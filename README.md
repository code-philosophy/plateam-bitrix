# plateam-bitrix

Модуль **1С-Битрикс** для системы **ПЛАТИМ** (`plateam.partner` + компоненты `plateam:*`).

Подключает интернет-магазин к production-платформе [`https://pla.team`](https://pla.team): виджет сертификатов, apply в корзине, hold/paid через Partner API.

Тест и бой на одном URL: в кабинете партнёра сначала `pk_test_…`, после проверки — `pk_live_…`. Менять origin/API не нужно.

| | |
| --- | --- |
| Версия | **1.1.9** |
| MODULE_ID | `plateam.partner` |
| Совместимость | PHP **7.4+**, Bitrix **main ≥ 18.0.0**, модуль **sale** |
| Репозиторий | [code-philosophy/plateam-bitrix](https://github.com/code-philosophy/plateam-bitrix) |

## Быстрый старт

1. Скачать [plateam.partner.zip](https://github.com/code-philosophy/plateam-bitrix/releases/latest/download/plateam.partner.zip) и распаковать в `/local/modules/` — получится `/local/modules/plateam.partner/`. Архив пересобирается на каждый push в `main`.
2. Админка → **Marketplace → Установленные решения** → **PLATEAM Partner** → Установить  
   (компоненты копируются в `/local/components/plateam` автоматически)
3. **Настройки модулей → PLATEAM Partner:** код партнёра и API key (`pk_test_` / `pk_live_`). Виджет и API всегда на `https://pla.team`
4. В шаблон: `plateam:widget`; в корзину: `plateam:checkout`
5. Кнопка **Проверить ключ** (смотрите `keyEnv`)

Подробно: [INSTALL.md](INSTALL.md).

## Структура

```
modules/plateam.partner/     ← содержимое plateam.partner.zip
  install/components/plateam/
  lib/  options.php  tools/  …
INSTALL.md
devtools/                    ← внутренняя отладка команды (не в zip, не партнёрский онбординг)
  plateam.php                ← список заказов с полями PLATEAM (в корень сайта на dev)
scripts/pack-marketplace.sh  ← сборка zip; на main её запускает GitHub Actions
```

Виджет (`widget.js`) хостится на платформе: `https://pla.team/widget.js`.

## Credentials

Ключ API и код партнёра — из кабинета на `https://pla.team`. Origin витрины должен быть в whitelist Системы. Ключ храните только в настройках модуля (server-side).
