export type Friction = {
    field: string;
    token: string;
};

/**
 * Hidden bot-friction fields: a honeypot input a person never sees and the
 * signed timestamp the server issued when it rendered the form.
 */
export default function FormFrictionFields({
    friction,
}: {
    friction: Friction;
}) {
    return (
        <div aria-hidden="true" className="hidden">
            <input
                type="text"
                name={friction.field}
                tabIndex={-1}
                autoComplete="off"
                defaultValue=""
            />
            <input
                type="hidden"
                name="_friction"
                defaultValue={friction.token}
            />
        </div>
    );
}
