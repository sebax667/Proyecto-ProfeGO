# Despliegue de ProfeGo

La instalación de Ubuntu 22.04, configuración de Nginx/PHP 8.3, validación de
`VPS_KNOWN_HOSTS`, helpers privilegiados, sudoers restringido, despliegue exacto y
rollback están documentados en [`docs/DESPLIEGUE.md`](../docs/DESPLIEGUE.md).

No ejecutes el workflow contra producción hasta completar la preparación única del
servidor y validar la huella ED25519 fuera de GitHub Actions.
