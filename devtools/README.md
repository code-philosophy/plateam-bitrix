# Devtools (не входят в marketplace-пакет)

Скрипты и артефакты для пилота **east** / локальной отладки. Не копировать на боевой ИМ партнёра.

| Путь | Назначение |
| --- | --- |
| `tools/web_install.php` | Bootstrap модуля + demo options (north / app.demo.pla.team) |
| `tools/setup_demo_*` | Каталог и промокоды стенда |
| `tools/debug_*`, `test_*` | Отладка |
| `sale_payment/plateam_stub` | Тестовая платёжка |
| `demo_images/` | Картинки демо-товаров |
| `PaySystemInstaller.php` | Установка stub-платёжки |

Для marketplace zip используется только `modules/plateam.partner/` (см. `scripts/pack-marketplace.sh`).
