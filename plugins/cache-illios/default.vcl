vcl 4.1;

backend default {
    .host = "web";
    .port = "80";
}

acl purge {
    "localhost";
    "127.0.0.1";
}

sub vcl_recv {
    if (req.method == "PURGE") {
        if (!client.ip ~ purge) {
            return(synth(405, "PURGE not allowed"));
        }
        return (purge);
    }
}

sub vcl_synth {
    if (req.method == "PURGE") {
        set resp.status = 200;
        set resp.http.content-type = "text/plain";
        synthetic("PURGE executed for: " + req.url);
        return (deliver);
    }
}