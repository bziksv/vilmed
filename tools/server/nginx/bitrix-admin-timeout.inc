# >>> VM bitrix admin timeout
location ^~ /bitrix/admin/ {
    proxy_read_timeout 600s;
    proxy_send_timeout 600s;
    proxy_pass http://127.0.0.1:81;
    proxy_redirect http://127.0.0.1:81/ /;
    include /etc/nginx/proxy_params;
}
# <<< VM bitrix admin timeout
