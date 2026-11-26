# Devtools (не входят в marketplace-пакет)

Скрипты и артефакты для **внутренней** отладки команды PLATEAM / пилота east.  
**Не** копировать на боевой ИМ партнёра и **не** использовать как партнёрский онбординг.

Партнёры подключаются только к `https://pla.team` (sandbox/live ключи в одном кабинете).  
`*.demo.pla.team` — внутренняя песочница команды, не ЛК партнёра.

| Путь | Назначение |
| --- | --- |
| `tools/web_install.php` | Bootstrap модуля для внутреннего стенда (хосты всегда `pla.team`; `PLATEAM_DEVTOOLS_DEMO=1` только сидирует sandbox-ключ) |
| `tools/setup_demo_*` | Каталог и промокоды стенда |
| `tools/debug_*`, `test_*` | Отладка |
| `sale_payment/plateam_stub` | Тестовая платёжка |
| `demo_images/` | Картинки демо-товаров |
| `PaySystemInstaller.php` | Установка stub-платёжки |

Для marketplace zip используется только `modules/plateam.partner/` (см. `scripts/pack-marketplace.sh`).
