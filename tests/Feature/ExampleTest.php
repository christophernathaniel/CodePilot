<?php

test('the home page opens the snippet workspace', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('dashboard'));
});
