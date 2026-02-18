<?php

test('the application redirects unauthenticated users', function () {
    $response = $this->get('/');

    $response->assertRedirect();
});
