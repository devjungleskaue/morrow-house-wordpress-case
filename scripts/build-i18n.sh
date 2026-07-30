#!/usr/bin/env bash
#
# Regenerates the translation templates and compiles the catalogues.
#
# Run this after adding or changing a translatable string. The repository
# contract test walks the source and fails if a string is missing from a .pot,
# because a template that has fallen behind is worse than none: translators work
# from it, so a string it does not list is a string nobody can translate and
# nobody is told about.
#
# The .po files are not generated. Those are the translations themselves and are
# edited by hand or in a tool like Poedit; this only refreshes the templates
# they are written against and compiles the binary catalogues WordPress loads.
#
# WP-CLI comes from the same pinned image the rest of the tooling uses, so this
# needs Docker and nothing installed on the host.
set -Eeuo pipefail

# Every path below is a path inside the container. Git Bash on Windows rewrites
# an argument that starts with a slash into a Windows path before the process
# ever sees it, so /var/www/html arrives as C:/Program Files/Git/var/www/html
# and WP-CLI reports "Not a valid source directory". These two variables turn
# that off and are ignored everywhere else.
export MSYS_NO_PATHCONV=1
export MSYS2_ARG_CONV_EXCL='*'

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$repo_root"

compose() { docker compose --env-file .env.example "$@"; }

theme=/var/www/html/wp-content/themes/morrow-house
plugin=/var/www/html/wp-content/plugins/morrow-house-core

printf 'Regenerating templates.\n'
compose run --rm cli wp i18n make-pot "$theme" "$theme/languages/morrow-house.pot" \
  --domain=morrow-house --exclude=languages
compose run --rm cli wp i18n make-pot "$plugin" "$plugin/languages/morrow-house-core.pot" \
  --domain=morrow-house-core --exclude=languages

printf 'Compiling catalogues.\n'
compose run --rm cli wp i18n make-mo "$theme/languages"
compose run --rm cli wp i18n make-mo "$plugin/languages"

# The two conventions differ and getting them backwards fails silently: the file
# sits in the right folder, WordPress looks for a different name, and every
# string renders in English with nothing in the log. Themes load {locale}.mo.
# Plugins load {domain}-{locale}.mo.
for esperado in \
  "wp-content/themes/morrow-house/languages/pt_BR.mo" \
  "wp-content/plugins/morrow-house-core/languages/morrow-house-core-pt_BR.mo"; do
  if [[ ! -f "$esperado" ]]; then
    printf 'Missing %s. A theme catalogue is named for the locale alone; a plugin catalogue carries its domain first.\n' "$esperado" >&2
    exit 1
  fi
done

printf 'Templates and catalogues are current.\n'
