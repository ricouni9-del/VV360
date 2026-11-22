#!/usr/bin/env bash
# Gera o pacote de publicação do VV360 para a GoDaddy (public_html).
# Uso: scripts/empacotar.sh [nome-do-arquivo.zip]
set -euo pipefail

cd "$(dirname "$0")/.."
SAIDA="${1:-dist/vv360-godaddy.zip}"
mkdir -p "$(dirname "$SAIDA")"
rm -f "$SAIDA"

# Trava de segurança: a chave de instalação precisa estar vazia no pacote.
if grep -Eq "CRM_CHAVE_INSTALACAO\s*=\s*'[^']+'" dados/config.exemplo.php; then
  echo "ERRO: CRM_CHAVE_INSTALACAO não está vazia em dados/config.exemplo.php" >&2
  exit 1
fi

zip -rq "$SAIDA" . \
  -x ".git/*" "dist/*" "scripts/*" \
     "*.sqlite" "*.sqlite-*" \
     "roteiros/*" "crm/roteiros/*" \
     "docs/*" "crm/docs/*" \
     "vv-crm-dados/*" \
     "dados/config.php" \
     "chave-documentos.php" "**/chave-documentos.php" \
     "*.DS_Store" "*~"

echo "Pacote gerado: $SAIDA"
unzip -l "$SAIDA" | tail -1
