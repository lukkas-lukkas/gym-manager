# Gym Manager

Sistema para gerenciar academias: alunos, planos, matrículas, receitas, despesas, conta bancária e relatórios.

---

## 1. Stack

| Parte | Tecnologia |
|---|---|
| **Back-end** | [Laravel 13](https://laravel.com/docs) + PHP 8.5 |
| **Front-end** | [Vue 3](https://vuejs.org) + TypeScript + [Tailwind CSS 4](https://tailwindcss.com) |
| **Ponte entre os dois** | [Inertia.js v3](https://inertiajs.com) |

O sistema roda dentro do **Docker**, usando o **[Laravel Sail](https://laravel.com/docs/sail)**. Não sendo preciso instalar outras dependencias localmente alem do docker e git.

---

## 2. Requisitos

| Ferramenta | Para quê | Como conferir |
|---|---|---|
| [Docker Desktop](https://www.docker.com/products/docker-desktop/) | Rodar os containers | `docker --version` |
| [Git](https://git-scm.com/) | Baixar o código | `git --version` |

---

## 3. Instalação

### 3.1. Clone o projeto

```bash
git clone https://github.com/lukkas-lukkas/gym-manager.git
```

```bash
cd gym-manager
```

### 3.2. Crie o arquivo de configuração `.env`

```bash
cp .env.example .env
```

### 3.3. Instale as dependências do PHP

O Sail fica dentro da pasta `vendor/`, que ainda não existe. Por isso, a primeira instalação usa um container temporário só para rodar o Composer:

```bash
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html laravelsail/php84-composer:latest composer install --ignore-platform-reqs
```

### 3.4. Crie um atalho para o Sail (opcional, mas recomendado)

Sem atalho, todo comando começa com `./vendor/bin/sail`. Com o atalho, vira só `sail`:

```bash
echo "alias sail='sh \$([ -f sail ] && echo sail || echo vendor/bin/sail)'" >> ~/.zshrc && source ~/.zshrc
```

### 3.5. Suba os containers

```bash
sail up -d
```

### 3.6. Configurando a aplicação

```bash
sail artisan key:generate
```

Gera a chave de criptografia da aplicação. 

```bash
sail artisan migrate --seed
```

Cria as tabelas no banco (**migrate**) e preenche com uma academia de demonstração (**seed**), com 18 meses de história e 6 meses de contas futuras. Assim o dashboard e os relatórios já abrem com números que parecem reais:

- 1 usuário para fazer login
- 1 conta bancária, com o saldo batendo com tudo que foi pago
- Planos Mensal, Trimestral, Semestral e Anual, mais um Anual Promocional que já saiu de venda
- Centenas de alunos com o histórico de matrículas: alguns renovam, outros saem, e alguns estão com a mensalidade atrasada
- Receitas de cada matrícula, pagas no dia ou com alguns dias de atraso
- Despesas fixas de todo mês (aluguel, folha, energia, água etc.) e algumas avulsas (equipamentos, manutenção, reforma)

Todas as datas são calculadas a partir de hoje. O seed só roda com o banco **vazio**, porque rodar de novo duplicaria tudo. Para recomeçar do zero:

```bash
sail artisan migrate:fresh --seed
```

```bash
sail npm install
```

Instala as dependências do front-end (Vue, Tailwind etc.).

```bash
sail npm run build
```

Compila o front-end.

---

### Login

Agora abra no navegador: **http://localhost**

O seed cria um usuário de teste:

| Campo | Valor |
|---|---|
| E-mail | `test@example.com` |
| Senha | `password#123` |

---

## 6. Estrutura do código

```
app/
├── Console/Commands/   → comandos agendados (ex.: renovar matrículas vencidas)
├── Enums/              → listas fixas de valores (status, período de cobrança)
├── Http/
│   ├── Controllers/    → recebem a requisição e devolvem a página
│   └── Requests/       → validação dos formulários
├── Models/             → tabelas do banco (Member, Enrollment, Revenue...)
└── Reports/            → cálculos dos relatórios e do dashboard

database/
├── migrations/         → estrutura das tabelas
├── factories/          → geradores de dados falsos (usados em testes e seeds)
└── seeders/            → dados de exemplo

resources/js/
├── pages/              → uma pasta por tela (members, expenses, revenues...)
├── components/         → pedaços de tela reaproveitáveis
├── layouts/            → layout padrão (menu, cabeçalho)
└── types/              → tipos TypeScript

routes/
├── web.php             → todas as URLs do sistema
└── console.php         → agendamento dos comandos

tests/
├── Feature/            → testes que simulam o uso real (requisição → resposta)
└── Unit/               → testes de peças isoladas
```

### Conceitos do domínio

| No código | Na tela | O que é |
|---|---|---|
| `Member` | Alunos | Cliente da academia |
| `MembershipPlan` | Planos | Plano contratável (mensal, trimestral...) |
| `Enrollment` | Matrículas | Um aluno contratando um plano por um período |
| `Revenue` | Receitas | Dinheiro a receber (gerado pelas matrículas) |
| `Expense` | Despesas | Dinheiro a pagar |
| `BankAccount` | Conta bancária | Onde o saldo é atualizado quando algo é pago |

---

## 9. Tarefas agendadas

O sistema tem dois comandos que rodam automaticamente em produção (definidos em `routes/console.php`):

| Comando | Quando roda | O que faz |
|---|---|---|
| `enrollments:roll-over` | A cada hora | Renova as matrículas que venceram |
| `bank-accounts:verify` | Todo dia às 03:00 | Confere se o saldo da conta bate com os pagamentos |

Localmente, eles **não** rodam sozinhos. Para testar, rode na manualmente:

```bash
sail artisan enrollments:roll-over
```

```bash
sail artisan bank-accounts:verify
```
