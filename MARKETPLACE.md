# Публикация в Маркетплейс 1С-Битрикс

Этот репозиторий готовит **дистрибутив модуля**. Загрузка и модерация — в [кабинете партнёра](https://partners.1c-bitrix.ru/).

## Код партнёра

`MODULE_ID` = `plateam.partner` → в карточке партнёра Bitrix код должен быть **`plateam`**.

Уже задано в `install/index.php`:

- `PARTNER_NAME` = `PLATEAM`
- `PARTNER_URI` = `https://pla.team`

## Сборка zip

```bash
./scripts/pack-marketplace.sh
# → dist/plateam.partner-1.0.0.zip
```

В архиве только каталог `plateam.partner` (содержимое `modules/plateam.partner`).  
**Не** включаются: `devtools/`, корневые docs репо.

## Чеклист перед модерацией

1. Версия в `install/version.php` (сейчас `1.0.0`)
2. `InstallFiles` / `UnInstallFiles` копируют/удаляют `/local/components/plateam`
3. Нет `debug_*` / `web_install` / stub-платёжки в пакете
4. Дефолты: `https://pla.team`, пустые ключи/промо
5. `lang/ru` + `lang/en` для install и options
6. Карточка решения: название, описание, скриншоты, поддержка
7. Тест установки на чистом Bitrix + Sale → настройки → виджет в шаблоне
8. Монитор качества / требования Маркетплейса по гайду 1С-Битрикс

## После одобрения

Включить «Доступен в каталоге». Обновления — новые zip с bump `VERSION`.
