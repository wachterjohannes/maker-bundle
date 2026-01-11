{% extends 'base.html.twig' %}

{% block title %}Log in!{% endblock %}

{% block body %}
<form
    method="post"
    action="{{ path('app_login') }}"
    data-controller="webauthn--authentication"
    data-webauthn--authentication-options-url-value="{{ path('webauthn.controller.request.request.login') }}"
    data-webauthn--authentication-submit-via-form-value="true"
    data-webauthn--authentication-conditional-ui-value="true"
    data-action="submit->webauthn--authentication#authenticate"
>
    {% if error %}
        <div class="alert alert-danger">{{ error.messageKey|trans(error.messageData, 'security') }}</div>
    {% endif %}

    {% if app.user %}
        <div class="mb-3">
            You are logged in as {{ app.user.userIdentifier }}, <a href="{{ path('app_logout') }}">Logout</a>
        </div>
    {% endif %}

    <h1 class="h3 mb-3 font-weight-normal">Please sign in</h1>
    <label for="username"><?= $username_label ?></label>
    <input
        type="text"
        value="{{ last_username }}"
        name="username"
        id="username"
        class="form-control"
        autocomplete="username webauthn"
        required
        autofocus
        data-webauthn--authentication-target="username"
    >

    <input
        type="hidden"
        name="_assertion"
        id="_assertion"
        data-webauthn--authentication-target="result"
    >

    <input type="hidden" name="_csrf_token" value="{{ csrf_token('authenticate') }}">

    <button class="btn btn-lg btn-primary" type="submit">
        Sign in
    </button>
</form>
{% endblock %}
