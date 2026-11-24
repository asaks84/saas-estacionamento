import { useId } from 'react'

/**
 * Campo com o rótulo dentro (form-floating), no padrão do sistema atual.
 * Mostra o erro de validação que vem do servidor. obrigatorio: põe o * vermelho no rótulo
 * (a regra continua no servidor; o * só avisa a pessoa).
 */
export default function Campo({ rotulo, erro, ajuda, obrigatorio = false, className = '', as = 'input', children, ...props }) {
    const id = useId()
    const Tag = as
    const classe = as === 'select' ? 'form-select' : 'form-control'
    return (
        <div className={`form-floating mb-3 ${className}`}>
            <Tag
                id={id}
                className={`${classe}${erro ? ' is-invalid' : ''}`}
                placeholder=" "
                aria-invalid={erro ? 'true' : undefined}
                aria-required={obrigatorio ? 'true' : undefined}
                {...props}
            >
                {children}
            </Tag>
            <label htmlFor={id}>{rotulo}{obrigatorio && <span className="text-danger" aria-hidden="true"> *</span>}</label>
            {erro && <div className="invalid-feedback">{erro}</div>}
            {ajuda && !erro && <div className="form-text">{ajuda}</div>}
        </div>
    )
}
