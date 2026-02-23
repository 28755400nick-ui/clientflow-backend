# 🧪 TESTING COMPLETO — CLIENTFLOW
> PHPUnit (Backend) + Jest + React Testing Library (Frontend)

---

## POR QUÉ PHPUnit (y no Pest)

Pest es excelente para Laravel moderno, pero en este proyecto **PHPUnit ya está instalado y funcional**.
La migración a Pest requiere 2 comandos extra y una curva de aprendizaje mínima.
Si en el futuro quieres Pest, los tests que escribimos son compatibles — solo cambias la sintaxis.

**Decisión: PHPUnit con el TestCase personalizado que extiende `RefreshDatabase`.**
Esto garantiza una base de datos limpia en cada test.

---

## BACKEND (Laravel + PHPUnit)

### Setup — NO necesitas instalar nada extra

Los tests de backend usan SQLite in-memory (ya configurado en `phpunit.xml`):

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

Cada test tiene base de datos limpia gracias al trait `RefreshDatabase`.

### Estructura de tests creada

```
tests/
├── TestCase.php                    ← Base con actingWithJwt() helper
├── Feature/
│   ├── AuthTest.php                ← Login, refresh, logout, /me
│   └── ClientTest.php              ← CRUD, filtros, paginación, export
└── Unit/
    └── JwtServiceTest.php          ← Lógica de JWT aislada
```

### Ejecutar todos los tests

```bash
# Desde c:\Users\nica2\clientflow-api
php artisan test
# o equivalente:
composer test
```

### Ejecutar solo un archivo

```bash
php artisan test --filter AuthTest
php artisan test --filter ClientTest
php artisan test --filter JwtServiceTest
```

### Ejecutar con cobertura de código

```bash
php artisan test --coverage
# Requiere Xdebug o PCOV instalado
```

### Instalar PCOV para cobertura (más rápido que Xdebug)

```bash
sudo apt install -y php8.2-pcov
```

### Resultado esperado

```
PASS  Tests\Feature\AuthTest
✓ login with valid credentials returns tokens
✓ login with wrong password returns 401
✓ login with nonexistent email returns 401
✓ login with missing email returns 422
✓ login with missing password returns 422
✓ login with invalid email format returns 422
✓ login stores refresh token in database
✓ refresh with valid token returns new tokens
✓ refresh with invalid token returns 401
✓ refresh with access token returns 401
✓ refresh without token returns 401
✓ refresh token is rotated after use
✓ logout revokes all user refresh tokens
✓ logout without token returns 401
✓ me returns authenticated user
✓ me without token returns 401
✓ me with invalid token returns 401
✓ me with refresh token instead of access token returns 401

PASS  Tests\Feature\ClientTest
✓ index returns paginated clients
✓ index requires authentication
✓ index filters by name
✓ index filters by phone
✓ index does not return soft deleted clients
✓ index respects per_page parameter
✓ index caps per_page at 100
✓ store creates client with valid data
✓ store requires authentication
✓ store rejects duplicate email
✓ store rejects invalid phone format
✓ store rejects missing required fields
✓ store rejects invalid email format
✓ store rejects first name exceeding max length
✓ update modifies client data
✓ update allows same email for same client
✓ update rejects email already used by another client
✓ update returns 404 for nonexistent client
✓ destroy soft deletes client
✓ destroy returns 404 for nonexistent client
✓ destroy requires authentication
✓ export returns xlsx file
✓ export requires authentication
✓ export respects name filter

PASS  Tests\Unit\JwtServiceTest
✓ access token is valid jwt string
✓ access token payload contains user data
✓ access token expires in 15 minutes
✓ refresh token is stored in database
✓ refresh token payload has type refresh
✓ generating new refresh token revokes previous ones
✓ refresh token has unique jti
✓ validate refresh token returns user for valid token
✓ validate refresh token returns null for access token
✓ validate refresh token returns null for invalid string
✓ validate refresh token returns null for expired token
✓ validate refresh token returns null for revoked token
✓ revoke refresh token deletes specific token
✓ revoke all user tokens deletes all tokens for user
✓ decode token throws exception for tampered token

Tests: 49 passed
```

---

## FRONTEND (Next.js + Jest + React Testing Library)

### Setup — INSTALAR dependencias

```bash
# Desde c:\Users\nica2\clientflow-front
npm install --save-dev \
  jest \
  jest-environment-jsdom \
  @testing-library/react \
  @testing-library/jest-dom \
  @testing-library/user-event \
  @types/jest \
  ts-jest \
  next/jest
```

### Agregar script de test en package.json

Edita `package.json` y agrega en `scripts`:

```json
"test": "jest",
"test:watch": "jest --watch",
"test:coverage": "jest --coverage"
```

### Estructura de tests creada

```
src/__tests__/
├── services/
│   ├── auth.service.test.ts        ← Login, logout, me, isAuthenticated
│   └── clients.service.test.ts     ← getAll, create, update, delete, export
└── hooks/
    └── useClients.test.ts          ← fetchClients, create, update, delete
```

### Ejecutar tests

```bash
# Todos los tests
npm test

# Con watch mode (re-ejecuta al guardar)
npm run test:watch

# Con cobertura
npm run test:coverage
```

### Cobertura esperada

| Archivo | Cobertura |
|---------|-----------|
| auth.service.ts | ~90% |
| clients.service.ts | ~85% |
| useClients.ts | ~90% |

---

## AGREGAR MÁS TESTS (guía rápida)

### Test de componente React (ejemplo: LoginPage)

```typescript
// src/__tests__/components/LoginPage.test.tsx
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import LoginPage from '@/app/login/page';

jest.mock('@/services/auth.service', () => ({
  authService: {
    login: jest.fn(),
    isAuthenticated: jest.fn().mockReturnValue(false),
  },
}));

import { authService } from '@/services/auth.service';

describe('LoginPage', () => {
  it('renders email and password fields', () => {
    render(<LoginPage />);
    expect(screen.getByLabelText(/email/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/contraseña/i)).toBeInTheDocument();
  });

  it('shows error on failed login', async () => {
    const mockLogin = jest.mocked(authService.login);
    mockLogin.mockRejectedValue({
      response: { data: { message: 'Credenciales inválidas.' } },
    });

    render(<LoginPage />);
    fireEvent.change(screen.getByLabelText(/email/i), {
      target: { value: 'bad@test.com' },
    });
    fireEvent.change(screen.getByLabelText(/contraseña/i), {
      target: { value: 'wrong' },
    });
    fireEvent.click(screen.getByRole('button', { name: /ingresar/i }));

    await waitFor(() => {
      expect(screen.getByText('Credenciales inválidas.')).toBeInTheDocument();
    });
  });
});
```

### Test de useAuth hook

```typescript
// src/__tests__/hooks/useAuth.test.ts
import { renderHook, waitFor } from '@testing-library/react';
import { useAuth } from '@/hooks/useAuth';

jest.mock('@/services/auth.service', () => ({
  authService: {
    isAuthenticated: jest.fn(),
    me: jest.fn(),
    logout: jest.fn(),
  },
}));

import { authService } from '@/services/auth.service';
const mockRouter = { replace: jest.fn(), push: jest.fn() };
jest.mock('next/navigation', () => ({
  useRouter: () => mockRouter,
}));

describe('useAuth', () => {
  it('redirects to /login when not authenticated', async () => {
    jest.mocked(authService.isAuthenticated).mockReturnValue(false);

    const { result } = renderHook(() => useAuth());

    await waitFor(() => {
      expect(mockRouter.replace).toHaveBeenCalledWith('/login');
    });
  });

  it('loads user when authenticated', async () => {
    jest.mocked(authService.isAuthenticated).mockReturnValue(true);
    jest.mocked(authService.me).mockResolvedValue({
      id: 1,
      name: 'Admin',
      email: 'admin@test.com',
    });

    const { result } = renderHook(() => useAuth());

    await waitFor(() => {
      expect(result.current.user).toEqual({
        id: 1,
        name: 'Admin',
        email: 'admin@test.com',
      });
    });
  });
});
```
